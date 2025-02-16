# MCMod Search API

一个用于搜索 [MCMod](https://www.mcmod.cn/) 百科的 API 接口。

## 功能特点

- 支持模组名称、缩写、中文名搜索
- 返回最佳匹配结果和相关结果
- 智能评分系统，优先展示最相关的模组
- 支持跨域请求
- 返回格式化的 JSON 数据

## 安装

1. 克隆仓库到本地：
```bash
git clone https://github.com/your-username/mcmod-search-api.git
```

2. 将文件放置在支持 PHP 的 Web 服务器目录下。

3. 确保 PHP 版本 >= 7.4 。

## API 使用说明

### 基本信息
- 基础URL：`http://你的域名地址.com/search_api.php`
- 请求方式：GET
- 返回格式：JSON

### 接口列表

#### 1. 获取 API 使用说明
```
GET /search_api.php
```

返回 API 的详细使用说明文档。

#### 2. 搜索模组
```
GET /search_api.php?key={search_term}
```

参数：
- `key`：搜索关键词（必填）

### 返回数据格式

```json
{
    "success": true,                // 是否成功
    "search_term": "搜索关键词",    // 搜索的关键词
    "timestamp": "2025-02-16 15:28:47",  // 搜索时间
    "total_results": 30,           // 搜索结果总数
    "best_result": {               // 最佳匹配结果
        "score": 174.4,            // 相关度评分
        "address": "https://www.mcmod.cn/class/2.html",  // 模组页面地址
        "title": "[IC2] 工业时代2 (Industrial Craft 2)",  // 模组标题
        "description": "模组描述...",  // 模组描述
        "snapshot_time": "2025-02-12",  // 数据更新时间
        "data": {                  // 模组详细数据
            "mcmod_id": "2",       // MCMod 站内 ID
            "abbr": "IC2",         // 模组缩写（可能为 null）
            "chinese_name": "工业时代2",  // 模组中文名
            "sub_name": "Industrial Craft 2"  // 模组英文名/副标题（可能为 null）
        }
    },
    "other_results": [             // 其他相关结果
        // 格式同 best_result
    ]
}
```

#### 字段说明

| 字段名 | 类型 | 说明 |
|--------|------|------|
| success | boolean | 请求是否成功 |
| search_term | string | 搜索的关键词 |
| timestamp | string | 搜索执行时间 |
| total_results | integer | 搜索结果总数 |
| best_result | object | 最佳匹配结果 |
| best_result.score | number | 结果相关度评分 |
| best_result.address | string | 模组页面完整地址 |
| best_result.title | string | 模组完整标题 |
| best_result.description | string | 模组描述文本 |
| best_result.snapshot_time | string | 数据快照更新时间 |
| best_result.data.mcmod_id | string | MCMod 站内唯一 ID |
| best_result.data.abbr | string\|null | 模组缩写，如无则为 null |
| best_result.data.chinese_name | string | 模组中文名称 |
| best_result.data.sub_name | string\|null | 模组英文名或副标题，如无则为 null |
| other_results | array | 其他相关结果数组，每个元素格式同 best_result |

### 示例图片

#### 1. 访问 API 根地址
![API 使用说明](docs/images/api_docs.png)

当直接访问 API 根地址时，会返回详细的 API 使用说明文档。

#### 2. 搜索模组示例
![搜索结果示例](docs/images/search_example.png)

使用关键词"IC2"搜索时的返回结果示例。

### 示例请求

```bash
# 搜索 IC2 模组
curl "http://你的域名地址.com/search_api.php?key=IC2"

# 搜索工业时代2
curl "http://你的域名地址.com/search_api.php?key=工业时代2"
```

## 评分系统

搜索结果使用以下因素进行评分：
1. 模组 ID（越小分数越高）
2. 标题匹配度
3. 描述完整度
4. 是否有中文名
5. 是否为主模组

## 开发说明

### 文件结构
- `search_api.php`：API 主程序
- `simple_html_dom.php`：HTML 解析库

### 依赖
- PHP >= 7.4
- [Simple HTML DOM Parser](http://simplehtmldom.sourceforge.net/)

## 许可证

本项目采用 MIT 许可证。详见 [LICENSE](LICENSE) 文件。

## 致谢

- [Simple HTML DOM Parser](http://simplehtmldom.sourceforge.net/) - HTML 解析库
- [MCMod](https://www.mcmod.cn/) - Minecraft 模组百科

## 贡献

欢迎提交 Issue 和 Pull Request！

1. Fork 本仓库
2. 创建新的功能分支
3. 提交代码
4. 创建 Pull Request

## 免责声明

本项目仅用于学习和研究目的，请遵守 MCMod 的使用条款和规范。