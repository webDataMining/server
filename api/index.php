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
    $recommend_answer = null;
    // 百度搜索推荐答案
    $baidu_search = request_get("http://www.baidu.com/s", array("wd" => $text));
    $html = str_get_html($baidu_search);
    // eg: 世界上最高的山是什么
    $detailed_answer = $html->find("div[class=op_exactqa_s_answer]", 0);
    if (!$detailed_answer) {
        // eg: 白日依山尽的下一句是什么
        $detailed_answer = $html->find("div[class=op_exactqa_detail_s_answer]", 0);
    }
    if ($detailed_answer) {
        $detailed_answer->find("span[class=op_exactqa_s_answer_property]", 0)->innertext = "";
        $recommend_answer = $detailed_answer->plaintext;
    } else {
        // eg: 儿童节在哪一天
        $calendar = $html->find("div[class*=op-calendar-content]", 0);
        if ($calendar) {
            $recommend_answer = $calendar->find("span", 0)->plaintext;
        } else {
            // eg: 北京大学的邮编是多少
            $postcode = $html->find("div[class*=op_post_content]", 0);
            if ($postcode) {
                $recommend_answer = intval($postcode->find("p", 0)->plaintext);
            }
        }
    }
    add_recommend($result, $recommend_answer);

    // 搜狗搜索推荐答案
    $sogou_search = request_get("https://www.sogou.com/web", array("query" => $text));
    $html = str_get_html($sogou_search);
    // eg: 奥巴马是哪国人
    $answer_box = $html->find("div[class=proInfoBox]", 0);
    if (!$answer_box) {
        // eg: 白日依山尽的下一句是什么
        $answer_box = $html->find("div[id=kmap_preciseqa_content]", 0);
    }
    if ($answer_box) {
        $recommend_answer = $answer_box->find("h4", 0)->plaintext;
    } else {
        $answer_box = $html->find("div[class=pic-txt-box]", 0);
        if ($answer_box) {
            // eg: 世界上最高的山是什么
            $detailed_answer = $answer_box->find("p[class=txt-pstature]", 0);
            if ($detailed_answer) {
                $detailed_answer->find("span", 0)->innertext = "";
                $recommend_answer = $detailed_answer->plaintext;
            } else {
                // eg: 哪个海峡沟通了北冰洋与太平洋
                $detailed_answer = $answer_box->find("div[class=txt-box]", 0);
                if ($detailed_answer) {
                    $recommend_answer = $detailed_answer->find("h4", 0)->plaintext;
                }
            }
        } else {
            // eg: 苹果公司的客服电话是什么
            $answer_box = $html->find("table[class=vr_serviceinfo]", 0);
            if ($answer_box) {
                $recommend_answer = $answer_box->plaintext;
            } else {
                // eg: 冬泳下水之前饮用白酒可以御寒吗
                $answer_box = $html->find("div[id=lizhi_mutex_wrapper]", 0);
                if ($answer_box) {
                    $answer_title = $answer_box->find("li[class=tab-resize cur]", 0);
                    $answer_title->find("span", 0)->innertext = "";
                    $recommend_answer = $answer_title->plaintext;
                }
            }
        }
    }
    add_recommend($result, $recommend_answer);
    // 搜狗搜索
    foreach($html->find("div[class=rb]") as $element) {
        $element_title = $element->find("h3[class=pt]", 0)->plaintext;
        $element_text = $element->find("div[class=ft]", 0)->plaintext;
        add_search_result($result, $element_title, $element_text);
    }
    foreach($html->find("div[class=vrwrap]") as $element) {
        $element_title = $element->find("h3[class=vrTitle]", 0)->plaintext;
        $element_text = $element->find("p[class=str_info]", 0)->plaintext;
        add_search_result($result, $element_title, $element_text);
    }

    // 百度知道搜索
    $baidu_zhidao = request_get("https://zhidao.baidu.com/search", array("word" => $text));
    $html = str_get_html($baidu_zhidao);
    foreach($html->find("dl[class=dl]") as $element) {
        $element_title = $element->find("dt[class=dt mb-4 line]", 0)->plaintext;
        $element_text = $element->find("dd[class=dd answer]", 0)->plaintext;
        $element_text = preg_replace("(^ 推荐答案|^答：|\[详细\] $)", "", $element_text);
        add_search_result($result, $element_title, $element_text);
    }

    // bing搜索推荐答案
    $bing_search = request_get("http://cn.bing.com/search", array("q" => $text));
    $html = str_get_html($bing_search);
    // eg: 意大利的官方语言
    $recommend_answer = $html->find("div[class=b_xlText b_emphText]", 0)->plaintext;
    if (strlen($recommend_answer) == 0) {
        // eg: 北京大学的地址在哪里
        $recommend_answer = $html->find("div[class=b_secondaryFocus b_emphText]", 1)->plaintext;
        if (strlen($recommend_answer) == 0) {
            // eg: 一公里等于多少米
            $recommend_answer = $html->find("div[class=b_focusTextSmall b_emphText]", 0)->plaintext;
        }
    }
    add_recommend($result, $recommend_answer);
    // bing搜索
    foreach($html->find("li[class=b_algo]") as $element) {
        $element_title = $element->find("h2", 0)->plaintext;
        $element_text = $element->find("div[class=b_caption]", 0)->find("p", 0)->plaintext;
        add_search_result($result, $element_title, $element_text);
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
        if (preg_match("(\(消歧义\)$)", $doc_title)) {
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

function add_recommend(&$result, &$recommend_answer, $reliability = 0.99) {
    if (strlen($recommend_answer) > 0) {
        $recommend_answer = preg_replace("((^\s*)|(\s*$))", "", $recommend_answer);
        $recommend_item = array("answer" => html_entity_decode($recommend_answer), "reliability" => $reliability);
        array_push($result["recommends"], $recommend_item);
        $recommend_answer = null;
    }
}

function add_search_result(&$result, $title, $text) {
    if (strlen($title) > 0 && strlen($text) > 0) {
        array_push($result["results"], array("title" => html_entity_decode($title), "text" => html_entity_decode($text)));
    }
}

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
