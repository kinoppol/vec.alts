<?php
/**
 * Transfers staff accounts in from an RMS installation.
 *
 * Only the origin of the source system is configurable (stored in settings as
 * rms_base_url, editable by the central administrator). The endpoint path and
 * the directory that serves profile pictures are part of the integration, so
 * they live here in the code.
 */
class RmsImporter
{
    /** Appended to the configured base URL to reach the people feed. */
    const API_PATH = '/api_connection.php?app_name=nutty&data=people';

    /** Appended to the configured base URL to reach an uploaded picture. */
    const FILES_PATH = '/files/';

    /** Marks rows in `users` as having come from this integration. */
    const SOURCE = 'rms';

    /** Image types accepted for a downloaded profile picture. */
    private static $imageTypes = array(
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
    );

    /**
     * A profile picture larger than this is not downloaded. Photographs taken
     * on a phone and uploaded unedited run to several megabytes, so the
     * ceiling has to sit above that to be useful.
     */
    const MAX_IMAGE_BYTES = 8388608; // 8 MB

    /** @var Repository */
    private $repo;

    /** @var string */
    private $baseUrl;

    public function __construct(Repository $repo, $baseUrl)
    {
        $this->repo = $repo;
        $this->baseUrl = rtrim(trim((string) $baseUrl), '/');
    }

    /**
     * @return string the full feed URL, for display and for fetching
     */
    public function feedUrl()
    {
        return $this->baseUrl . self::API_PATH;
    }

    /**
     * Where downloaded pictures are written.
     * @return string
     */
    public static function avatarDir()
    {
        return VEC_ROOT . '/uploads/avatars';
    }

    /**
     * Downloads and decodes the people feed.
     *
     * @return array array('ok'=>bool, 'error'=>string, 'people'=>array, 'total'=>int, 'skipped_exit'=>int)
     */
    public function fetch()
    {
        $out = array('ok' => false, 'error' => '', 'people' => array(),
            'total' => 0, 'skipped_exit' => 0);

        $error = Http::validateUrl($this->baseUrl);
        if ($error !== '') {
            $out['error'] = $error;
            return $out;
        }

        $response = Http::get($this->feedUrl());
        if (!$response['ok']) {
            $out['error'] = $response['error'];
            return $out;
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            $out['error'] = 'ข้อมูลที่ได้รับไม่ใช่ JSON ที่ถูกต้อง (' . json_last_error_msg() . ')';
            return $out;
        }

        // The feed hands back each row with both numeric and named keys, the
        // shape mysqli_fetch_array() produces. Only the named ones are used.
        foreach ($decoded as $row) {
            if (!is_array($row) || !isset($row['people_id'])) {
                continue;
            }
            $out['total']++;

            // Anyone who has left the organisation is not transferred.
            if ((string) arr($row, 'people_exit', '0') !== '0') {
                $out['skipped_exit']++;
                continue;
            }
            $out['people'][] = $row;
        }

        $out['ok'] = true;
        return $out;
    }

    /**
     * Transfers the people into `users`.
     *
     * @param array $people rows from fetch()
     * @param array $options school_id, role, update_passwords, avatars, deadline
     * @return array counts and notes
     */
    public function import($people, $options = array())
    {
        $schoolId = arr($options, 'school_id');
        $schoolId = ($schoolId === null || $schoolId === '') ? null : (int) $schoolId;
        $role = arr($options, 'role', 'advisor');
        $roles = staff_roles();
        if (!isset($roles[$role])) {
            $role = 'advisor';
        }
        $updatePasswords = !empty($options['update_passwords']);
        $withAvatars = !empty($options['avatars']);
        $deadline = arr($options, 'deadline', time() + 20);

        $result = array(
            'created' => 0, 'updated' => 0, 'failed' => 0,
            'no_password' => 0, 'no_email' => 0,
            'avatar_saved' => 0, 'avatar_failed' => 0, 'avatar_pending' => 0,
            'avatar_reasons' => array(),
            'skipped_exit' => 0,
            'errors' => array(),
        );

        foreach ($people as $row) {
            $externalId = trim((string) arr($row, 'people_id', ''));
            if ($externalId === '') {
                $result['failed']++;
                continue;
            }

            // fetch() has already dropped these, but the rule that someone who
            // has left is never transferred belongs next to the write as well,
            // so no future caller can bypass it by assembling its own list.
            if ((string) arr($row, 'people_exit', '0') !== '0') {
                $result['skipped_exit']++;
                continue;
            }

            $fullName = trim(
                trim((string) arr($row, 'people_name', ''))
                . ' '
                . trim((string) arr($row, 'people_surname', ''))
            );

            $email = trim((string) arr($row, 'people_email', ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Stored as NULL so the unique index tolerates any number of
                // people without an address.
                $email = null;
                $result['no_email']++;
            }

            $plainPassword = (string) arr($row, 'ath_pass', '');
            $hasPassword = ($plainPassword !== '');
            if (!$hasPassword) {
                // No credential to carry over. A random one keeps the column
                // valid and unguessable until an administrator sets a real one.
                $plainPassword = vec_random_token(24);
                $result['no_password']++;
            }

            try {
                $outcome = $this->repo->upsertImportedUser(array(
                    'external_source' => self::SOURCE,
                    'external_id'     => $externalId,
                    'username'        => $externalId,
                    'full_name'       => $fullName !== '' ? $fullName : $externalId,
                    'email'           => $email,
                    'password'        => $plainPassword,
                    'phone'           => trim((string) arr($row, 'people_mobile', '')),
                    'school_id'       => $schoolId,
                    'role'            => $role,
                ), $updatePasswords);
            } catch (PDOException $e) {
                $result['failed']++;
                if (count($result['errors']) < 25) {
                    $result['errors'][] = $externalId . ': ' . $e->getMessage();
                }
                continue;
            }

            if ($outcome['created']) {
                $result['created']++;
            } else {
                $result['updated']++;
            }

            if (!$withAvatars) {
                continue;
            }

            $picture = trim((string) arr($row, 'people_pic', ''));
            if ($picture === '') {
                // No picture in the source; the interface falls back to
                // the person's initials.
                continue;
            }
            if (time() >= $deadline) {
                $result['avatar_pending']++;
                continue;
            }

            $saved = $this->downloadAvatar($picture, $outcome['id'], $outcome['avatar_path']);
            if ($saved['ok']) {
                $result['avatar_saved']++;
            } else {
                $result['avatar_failed']++;
                $this->noteReason($result, $saved['reason']);
            }
        }

        return $result;
    }

    /**
     * Tallies why pictures failed, so the screen can name the cause instead
     * of only counting failures.
     *
     * @param array $result
     * @param string $reason
     */
    private function noteReason(&$result, $reason)
    {
        if (!isset($result['avatar_reasons'])) {
            $result['avatar_reasons'] = array();
        }
        if (!isset($result['avatar_reasons'][$reason])) {
            $result['avatar_reasons'][$reason] = 0;
        }
        $result['avatar_reasons'][$reason]++;
    }

    /**
     * Whether the server is able to store downloaded pictures at all.
     *
     * Checked up front and reported plainly, because the usual cause of a
     * whole run failing is a directory the web server cannot write to, and
     * "N pictures failed" gives the operator nothing to act on.
     *
     * @return array array('ok'=>bool, 'error'=>string, 'dir'=>string)
     */
    public static function storageStatus()
    {
        $dir = self::avatarDir();

        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true)) {
                return array(
                    'ok'    => false,
                    'dir'   => $dir,
                    'error' => 'ไม่มีโฟลเดอร์เก็บรูป และสร้างอัตโนมัติไม่ได้',
                );
            }
        }
        if (!is_writable($dir)) {
            return array(
                'ok'    => false,
                'dir'   => $dir,
                'error' => 'โฟลเดอร์เก็บรูปไม่มีสิทธิ์เขียน',
            );
        }

        // is_writable() can still be wrong under SELinux or an open_basedir
        // restriction, so prove it by actually writing something.
        $probe = $dir . DIRECTORY_SEPARATOR . 'write-test-' . vec_random_token(6);
        if (@file_put_contents($probe, 'x') === false) {
            return array(
                'ok'    => false,
                'dir'   => $dir,
                'error' => 'เขียนไฟล์ลงโฟลเดอร์เก็บรูปไม่สำเร็จ (อาจติด SELinux หรือ open_basedir)',
            );
        }
        @unlink($probe);

        return array('ok' => true, 'dir' => $dir, 'error' => '');
    }

    /**
     * Fetches one profile picture and records it against the user.
     *
     * @param string $picture value of people_pic
     * @param int $userId
     * @param string $currentPath avatar already stored, if any
     * @return array array('ok'=>bool, 'file'=>string, 'reason'=>string)
     */
    public function downloadAvatar($picture, $userId, $currentPath = '')
    {
        $fail = function ($reason) {
            return array('ok' => false, 'file' => '', 'reason' => $reason);
        };

        $relative = $this->safePicturePath($picture);
        if ($relative === '') {
            return $fail('ชื่อไฟล์รูปในระบบ RMS ไม่ปลอดภัยหรือไม่ถูกต้อง');
        }

        $response = Http::get($this->baseUrl . self::FILES_PATH . $relative, self::MAX_IMAGE_BYTES);
        if (!$response['ok']) {
            return $fail('ดึงไฟล์รูปไม่สำเร็จ: ' . $response['error']);
        }
        if ($response['body'] === '') {
            return $fail('ไฟล์รูปที่ได้รับว่างเปล่า');
        }

        $dir = self::avatarDir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return $fail('สร้างโฟลเดอร์เก็บรูปไม่สำเร็จ: ' . $dir);
        }

        // Write first, then ask what it actually is. The extension in the
        // source is not evidence of the contents.
        $temp = $dir . DIRECTORY_SEPARATOR . 'tmp-' . vec_random_token(8);
        if (@file_put_contents($temp, $response['body']) === false) {
            return $fail('เขียนไฟล์ลงโฟลเดอร์เก็บรูปไม่สำเร็จ (ตรวจสอบสิทธิ์ของ ' . $dir . ')');
        }

        $info = @getimagesize($temp);
        if (!is_array($info) || !isset($info[2]) || !isset(self::$imageTypes[$info[2]])) {
            @unlink($temp);
            return $fail('ไฟล์ที่ได้รับไม่ใช่รูปภาพ JPEG/PNG/GIF');
        }
        $extension = self::$imageTypes[$info[2]];

        // Deterministic name, so transferring the same person twice replaces
        // the picture instead of accumulating copies.
        $filename = sha1(self::SOURCE . '|' . $userId) . '.' . $extension;
        $target = $dir . DIRECTORY_SEPARATOR . $filename;

        if (is_file($target)) {
            @unlink($target);
        }
        if (!@rename($temp, $target)) {
            @unlink($temp);
            return $fail('ย้ายไฟล์รูปไปยังปลายทางไม่สำเร็จ');
        }
        @chmod($target, 0644);

        // A picture that changed type leaves the previous file behind.
        if ($currentPath !== '' && $currentPath !== $filename) {
            $old = $dir . DIRECTORY_SEPARATOR . basename($currentPath);
            if (is_file($old)) {
                @unlink($old);
            }
        }

        $this->repo->setUserAvatar($userId, $filename);
        return array('ok' => true, 'file' => $filename, 'reason' => '');
    }

    /**
     * Turns people_pic into a path safe to append to the files URL.
     *
     * @param string $picture
     * @return string '' when the value cannot be trusted
     */
    private function safePicturePath($picture)
    {
        $picture = str_replace('\\', '/', trim((string) $picture));
        if ($picture === '' || strpos($picture, '..') !== false) {
            return '';
        }
        // An absolute URL in the field would point somewhere else entirely.
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $picture)) {
            return '';
        }
        $picture = ltrim($picture, '/');

        $segments = array();
        foreach (explode('/', $picture) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            $segments[] = rawurlencode($segment);
        }
        return implode('/', $segments);
    }

    /**
     * Downloads pictures for people transferred earlier whose picture is still
     * missing, so a run cut short by the time budget can be continued.
     *
     * @param array $people rows from fetch()
     * @param int $deadline
     * @return array
     */
    public function catchUpAvatars($people, $deadline)
    {
        $result = array('saved' => 0, 'failed' => 0, 'pending' => 0, 'avatar_reasons' => array());

        // A directory the web server cannot write to fails every single
        // picture; say so once rather than several hundred times.
        $storage = self::storageStatus();
        if (!$storage['ok']) {
            $result['blocked'] = $storage['error'] . ' — ' . $storage['dir'];
            return $result;
        }

        $byExternalId = array();
        foreach ($people as $row) {
            $id = trim((string) arr($row, 'people_id', ''));
            $pic = trim((string) arr($row, 'people_pic', ''));
            if ($id !== '' && $pic !== '') {
                $byExternalId[$id] = $pic;
            }
        }
        if (!$byExternalId) {
            return $result;
        }

        foreach ($this->repo->usersMissingAvatar(self::SOURCE) as $user) {
            $externalId = (string) $user['external_id'];
            if (!isset($byExternalId[$externalId])) {
                continue;
            }
            if (time() >= $deadline) {
                $result['pending']++;
                continue;
            }
            $saved = $this->downloadAvatar($byExternalId[$externalId], (int) $user['id'], '');
            if ($saved['ok']) {
                $result['saved']++;
            } else {
                $result['failed']++;
                $this->noteReason($result, $saved['reason']);
            }
        }
        return $result;
    }
}
