# Server for Web Data Mining Project

## API 总体说明

* 所有请求均为 HTTP 的 GET 请求，返回 JSON 格式数据
* 返回码 `code` 为 `0` 时表示请求成功，`data` 字段为返回的数据，否则返回一个对应的错误信息 `message`
* 所有情况下，以下错误码对应固定的含义：
	* `-110` 未知错误
	* `-100` 参数非法
	* `-50` 服务器错误
	* `-1` 参数缺失

## 搜索

###### 网址

* `/api`

###### 参数

* `range` 查询范围，取 `online` 或者 `local`，`online` 从百度，搜狗，百度知道和bing上搜索，`local` 从本地solr搜中文维基百科
* `title` 匹配词条标题，仅在本地查询需要，优先级高于 `text`，返回前5个词条，不能超过50字节
* `text` 问题全文，返回前10个词条，不能超过200字节

###### 返回

* `recommends` 数组，每个元素如下，可为空
 * `answer` 答案正文
 * `text` 答案置信度，范围为0~1

* `results` 数组，每个元素如下
 * `title` 结果标题
 * `text` 结果正文，维基百科已提取正文
 * `meta_boxes` 可选，额外信息，格式不定
 
* `warnings` 数组，每个元素是字符串，表示处理过程中的警告