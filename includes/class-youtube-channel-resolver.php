<?php
/**
 * YouTube チャンネル入力（ID / URL / @ハンドル）をチャンネル ID (UC…) に正規化
 *
 * @package NewsCrawler
 */

if (!defined('ABSPATH')) {
    exit;
}

class News_Crawler_Youtube_Channel_Resolver {

    private const CHANNEL_ID_PATTERN = '/^UC[a-zA-Z0-9_-]{22}$/';

    /**
     * 複数行をチャンネル ID に正規化
     *
     * @param array  $lines   1行1チャンネル
     * @param string $api_key YouTube Data API v3 キー（URL・ハンドル解決に必要）
     * @return array{channels: string[], errors: string[]}
     */
    public static function normalize_lines(array $lines, $api_key = '') {
        $channels = array();
        $errors = array();

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $id = self::resolve($line, $api_key);
            if ($id !== null && !in_array($id, $channels, true)) {
                $channels[] = $id;
            } else {
                $errors[] = $line;
            }
        }

        return array(
            'channels' => $channels,
            'errors'   => $errors,
        );
    }

    /**
     * 1件の入力をチャンネル ID に解決
     *
     * @param string $input
     * @param string $api_key
     * @return string|null
     */
    public static function resolve($input, $api_key = '') {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        if (self::is_channel_id($input)) {
            return $input;
        }

        if (preg_match('#/channel/(UC[a-zA-Z0-9_-]{22})#i', $input, $matches)) {
            return $matches[1];
        }

        $handle = null;
        if (preg_match('#youtube\.com/@([a-zA-Z0-9._-]+)#i', $input, $matches)) {
            $handle = $matches[1];
        } elseif (preg_match('/^@([a-zA-Z0-9._-]+)$/', $input, $matches)) {
            $handle = $matches[1];
        }

        if ($handle !== null) {
            return self::fetch_channel_id_by_handle($handle, $api_key);
        }

        if (preg_match('#youtube\.com/user/([a-zA-Z0-9._-]+)#i', $input, $matches)) {
            return self::fetch_channel_id_by_username($matches[1], $api_key);
        }

        if (preg_match('#youtube\.com/c/([a-zA-Z0-9._-]+)#i', $input, $matches)) {
            $slug = $matches[1];
            $id = self::fetch_channel_id_by_handle($slug, $api_key);
            if ($id !== null) {
                return $id;
            }
            return self::fetch_channel_id_by_custom_slug($slug, $api_key);
        }

        // プレーン URL 以外のスラッグ（ハンドル扱い）
        if (!preg_match('#https?://#i', $input) && preg_match('/^[a-zA-Z0-9._-]{3,}$/', $input)) {
            $id = self::fetch_channel_id_by_handle($input, $api_key);
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param string $value
     * @return bool
     */
    public static function is_channel_id($value) {
        return (bool) preg_match(self::CHANNEL_ID_PATTERN, trim((string) $value));
    }

    /**
     * @param string $handle @ なし
     * @param string $api_key
     * @return string|null
     */
    private static function fetch_channel_id_by_handle($handle, $api_key) {
        $handle = ltrim((string) $handle, '@');
        if ($handle === '' || empty($api_key)) {
            return null;
        }

        $data = self::api_request(
            $api_key,
            array(
                'part'      => 'id',
                'forHandle' => $handle,
            )
        );

        return self::first_channel_id_from_response($data);
    }

    /**
     * @param string $username
     * @param string $api_key
     * @return string|null
     */
    private static function fetch_channel_id_by_username($username, $api_key) {
        if ($username === '' || empty($api_key)) {
            return null;
        }

        $data = self::api_request(
            $api_key,
            array(
                'part'        => 'id',
                'forUsername' => $username,
            )
        );

        return self::first_channel_id_from_response($data);
    }

    /**
     * /c/ カスタム URL 用（ハンドルで取れない場合の検索フォールバック）
     *
     * @param string $slug
     * @param string $api_key
     * @return string|null
     */
    private static function fetch_channel_id_by_custom_slug($slug, $api_key) {
        if ($slug === '' || empty($api_key)) {
            return null;
        }

        $url = add_query_arg(
            array(
                'part'       => 'snippet',
                'type'       => 'channel',
                'q'          => $slug,
                'maxResults' => 5,
                'key'        => $api_key,
            ),
            'https://www.googleapis.com/youtube/v3/search'
        );

        $response = wp_remote_get($url, array('timeout' => 15));
        if (is_wp_error($response)) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['items'])) {
            return null;
        }

        $slug_lower = strtolower($slug);
        foreach ($data['items'] as $item) {
            if (($item['id']['kind'] ?? '') !== 'youtube#channel') {
                continue;
            }
            $channel_id = $item['id']['channelId'] ?? '';
            if (!self::is_channel_id($channel_id)) {
                continue;
            }
            $title = strtolower($item['snippet']['channelTitle'] ?? '');
            $custom = strtolower($item['snippet']['customUrl'] ?? '');
            if ($custom === $slug_lower || $title === $slug_lower) {
                return $channel_id;
            }
        }

        $first = $data['items'][0];
        if (($first['id']['kind'] ?? '') === 'youtube#channel') {
            $channel_id = $first['id']['channelId'] ?? '';
            return self::is_channel_id($channel_id) ? $channel_id : null;
        }

        return null;
    }

    /**
     * @param string $api_key
     * @param array  $params
     * @return array|null
     */
    private static function api_request($api_key, array $params) {
        $params['key'] = $api_key;
        $url = add_query_arg($params, 'https://www.googleapis.com/youtube/v3/channels');

        $response = wp_remote_get($url, array('timeout' => 15));
        if (is_wp_error($response)) {
            return null;
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : null;
    }

    /**
     * @param array|null $data
     * @return string|null
     */
    private static function first_channel_id_from_response($data) {
        if (!is_array($data) || empty($data['items'][0]['id'])) {
            return null;
        }

        $id = $data['items'][0]['id'];
        return self::is_channel_id($id) ? $id : null;
    }
}
