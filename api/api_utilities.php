<?php
/**
 * Created by PhpStorm.
 * User: Frank
 * Date: 16/4/25
 * Time: 下午2:27
 */

require_once "../third-party/simple_html_dom.php";
require_once "../third-party/wiki_parser.php";

//** Error codes. **//
define('ERROR_UNKNOWN', -110);
define('ERROR_ILLEGAL_PARAMETER', -100);
define('ERROR_SERVER_ERROR', -50);
define('ERROR_TOO_FREQUENT', -10);
define('ERROR_MISSING_PARAMETER', -1);

/**
 * Stop reporting errors to client
 */
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

/**
 * @param int $code Error code
 * @param string $message Error message
 * @param bool $should_exit Whether php should exit after reporting the error
 */
function report_error($code = ERROR_UNKNOWN, $message = "", $should_exit = true) {
    if ($code == 0) { // 0 为成功代码
        $code = ERROR_UNKNOWN;
    }
    if (strlen($message) == 0) {
        switch ($code) {
            case ERROR_UNKNOWN:
                $message = "未知错误";
                break;
            case ERROR_ILLEGAL_PARAMETER:
                $message = "参数中含有非法字符";
                break;
            case ERROR_SERVER_ERROR:
                $message = "服务器错误";
                break;
            case ERROR_TOO_FREQUENT:
                $message = "请求过于频繁";
                break;
            case ERROR_MISSING_PARAMETER:
                $message = "参数缺失";
                break;
            default:
                break;
        }
    }
    echo json_encode(array("code" => $code, "message" => $message));
    if ($should_exit) {
        exit();
    }
}

/**
 * @param mixed $data Data to return
 */
function report_success($data = null) {
    echo json_encode(array("code" => 0, "data" => $data));
}

/**
 * @param string $baseurl Request base URL
 * @param array $get Request parameters
 * @return string Request result
 */
function request_get($baseurl, $get = null) {
    $options = array(
        "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
        ),
    );
    $url = $baseurl;
    if ($get != null) {
        $url .= "?" . http_build_query($get);
    }

    $raw_str = file_get_contents($url, false, stream_context_create($options));
    $encoding = mb_detect_encoding($raw_str, array('ASCII', 'UTF-8', 'GB2312', 'GBK'));
    if ($encoding != "UTF-8") {
        $raw_str = iconv($encoding, "UTF-8//IGNORE", $raw_str);
    }
    return $raw_str;
}
