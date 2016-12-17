<?php
/**
 * Created by PhpStorm.
 * User: Frank
 * Date: 2016/12/10
 * Time: 上午11:02
 */

require_once 'api_utilities.php';

$range = $_GET["range"];
$title = $_GET["title"];
$text = $_GET["text"];

if (strlen($range) == 0 || (strlen($title) == 0 && strlen($text) == 0)) {
    report_error(ERROR_MISSING_PARAMETER);
}

if (strlen($title) > 50 || strlen($text) > 200) {
    report_error(1, "查询内容过长");
}

if (!($range == "online" || $range == "local")) {
    report_error(ERROR_ILLEGAL_PARAMETER);
}

$result = array("recommends" => array(), "results" => array());
if ($range == "online") {
    $baidu_search = request_get("http://www.baidu.com/s", array("wd" => $text));
    $html = str_get_html($baidu_search);
    $recommend_answer = $html->find("div[class=op_exactqa_s_answer]", 0)->plaintext;
    if (strlen($recommend_answer) > 0) {
        $recommend_answer = preg_replace("((^\s*)|(\s*$))", "", $recommend_answer);
        array_push($result["recommends"], array("answer" => $recommend_answer, "reliability" => "1"));
    }

    $baidu_zhidao = request_get("https://zhidao.baidu.com/search", array("word" => $text));
    $html = str_get_html($baidu_zhidao);
    foreach($html->find("dl[class=dl]") as $element) {
        $question = $element->find("dt[class=dt mb-4 line]", 0)->plaintext;
        $answer = $element->find("dd[class=dd answer]", 0)->plaintext;
        $answer = preg_replace("(^ 推荐答案|^答：|\[详细\] $)", "", $answer);
        array_push($result["results"], array("title" => $question, "text" => $answer));
    }

    $bing_search = request_get("http://cn.bing.com/search", array("q" => $text));
    $html = str_get_html($bing_search);
    foreach($html->find("li[class=b_algo]") as $element) {
        $title = $element->find("h2", 0)->plaintext;
        $text = $element->find("div[class=b_caption]", 0)->find("p", 0)->plaintext;
        if (strlen($title) > 0 && strlen($text) > 0) {
            array_push($result["results"], array("title" => $title, "text" => $text));
        }
    }
} else if ($range == "local") {
    $search_title = strlen($title) > 0;
    $solr = get_wiki($title, $text, 30);
    $redirect_regex = "((?i)^#(redirect|重定向))";
    $processed_title = array();
    foreach ($solr as $doc) {
        $doc_title = $doc["title"];
        $doc_text = $doc["text"];

        if (preg_match($redirect_regex, $doc_text) > 0) {
            $doc_text = preg_replace($redirect_regex, "", $doc_text);
            $new_title = process_wiki($doc_text)["text"];
            if ($processed_title[$new_title]) {
                continue;
            }
            $new_solr = get_wiki($new_title, null, 1);
            $doc_title = $new_solr[0]["title"];
            $doc_text = $new_solr[0]["text"];
        }
        if ($processed_title[$doc_title]) {
            continue;
        }
        if (preg_match("((?i)^(wikipedia|portal|template|category):)", $doc_title)) {
            continue;
        }

        $processed_title[$doc_title] = true;
        array_push($result["results"], process_wiki($doc_text, $doc_title));
        if (count($result) >= ($search_title ? 5 : 10)) {
            break;
        }
    }
}
report_success($result);

function get_wiki($title, $text, $rows) {
    $params = array(
        "wt" => "json",
        "rows" => $rows,
    );
    if ($title != null) {
        $params["q"] = "titleText:" . $title;
    } else {
        $params["q"] = "text:" . $text;
    }
    $solr_raw = request_get("http://search.fanzhikang.cn/solr/wiki/select", $params);
    $solr = json_decode($solr_raw, true);
    return $solr["response"]["docs"];
}

function process_wiki($text, $title = null) {
    $wiki_parser = new Jungle_WikiSyntax_Parser($text, $title);
    $parse_raw = $wiki_parser->parse();
    $wiki_string = "";
    foreach ($parse_raw["sections"] as $section) {
        $section_title = $section["title"];
        $section_text = $section["text"];
        if (preg_match("(参考|参考(文献|资料)|注释|外部(链接|连接))", $section_title)) {
            continue;
        }
        if (strlen($section_title) > 0) {
            $wiki_string .= $section_title . ":\n";
        }
        if (strlen($section_text) > 0) {
            $wiki_string .= recursive_extract_wiki_text($section_text);
        }
        $wiki_string .= "\n";
    }
    $result = array("title" => $parse_raw["title"], "text" => $wiki_string);
    $meta_boxes = $parse_raw["meta_boxes"];
    if ($meta_boxes) {
        $result["meta_boxes"] = recursive_extract_wiki_text($meta_boxes);
    }
    return $result;
}

function recursive_extract_wiki_text($item) {
    $type = gettype($item);
    if ($type == "array") {
        $result = array();
        foreach ($item as $key => $value) {
            $result[$key] = recursive_extract_wiki_text($value);
        }
        return $result;
    } else {
        $text = $item;
        $text = preg_replace("(\[wiki=[a-zA-Z0-9]{32}\]|\[/wiki\])", "", $text);
        $text = preg_replace("(\[url=.*?\]|\[/url\])", "", $text);
        $text = preg_replace("((?i)\[\[(category|file|image):.*?\]\])", "", $text);
        $text = preg_replace("(<br {0,1}/>|\*)", "", $text);
        return $text;
    }
}
