<?php
header('Content-Type: application/json; charset=utf-8');

// 允许跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// 错误处理
function return_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'error' => $message,
        'code' => $code
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取最佳结果
function get_best_result($results) {
    if (empty($results)) {
        return [null, 0];
    }

    // 评分函数
    function score_result($result) {
        $score = 0;
        $max_possible_score = 155;  // 最高可能分数：50 + 40 + 30 + 20 + 15
        
        // 1. 有 mcmod_id 的结果更好
        if (!empty($result['data']['mcmod_id'])) {
            $score += 50;
            // mcmod_id 越小说明越早且知名
            try {
                $score += (10000 - intval($result['data']['mcmod_id'])) / 100;
            } catch (Exception $e) {
                // 忽略转换错误
            }
        }
        
        // 2. 标题完全匹配的结果更好
        if (!empty($result['title']) && !str_starts_with($result['title'], '(')) {
            $score += 30;
        }
        
        // 3. 有完整描述的结果更好
        if (!empty($result['description']) && strlen($result['description']) > 100) {
            $score += 20;
        }
        
        // 4. 有中文名的结果更好
        if (!empty($result['data']['chinese_name'])) {
            $score += 15;
        }
        
        // 5. 类别为模组本体的结果更好（category="1"）
        if (!empty($result['data']['category']) && $result['data']['category'] === "1") {
            $score += 40;
        }
        
        return $score;
    }

    // 对结果进行评分
    $scored_results = array_map(function($result) {
        return [
            'result' => $result,
            'score' => score_result($result)
        ];
    }, $results);

    // 按分数排序
    usort($scored_results, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    return [$scored_results[0]['result'], $scored_results[0]['score']];
}

// 提取数据
function extract_item_data($result_item) {
    $item_data = [];
    $data = [];
    
    try {
        // 提取地址
        $info_span = $result_item->find('span.info', 0);
        $address = $info_span->find('a', 0)->href;
        
        // 修改地址处理逻辑
        if (strpos($address, '//') === 0) {
            $address = 'https:' . $address;
        }
        
        // 统一使用 www.mcmod.cn 域名
        $address = str_replace([
            'center.mcmod.cn',
            'search.mcmod.cn'
        ], 'www.mcmod.cn', $address);
        
        // 修正斜杠
        $address = str_replace('\\', '/', $address);
        
        $item_data['address'] = $address;
        
        // 提取 mcmod_id
        if (preg_match('/\/(class|modpack)\/(\d+)\.html/', $address, $matches)) {
            $data['mcmod_id'] = $matches[2];
        } else {
            $data['mcmod_id'] = null;
        }

        // 提取标题
        $title = trim($result_item->find('div.head', 0)->text());
        $item_data['title'] = $title;
        
        // 处理标题信息
        if (strpos($title, '[') === 0) {
            $parts = explode('] ', $title, 2);
            $data['abbr'] = substr($parts[0], 1);
            $main_title = $parts[1];
        } else {
            $data['abbr'] = null;
            $main_title = $title;
        }

        // 提取中文名和副标题
        if (strpos($main_title, ' (') !== false) {
            list($chinese_name, $sub_name) = explode(' (', $main_title, 2);
            $data['chinese_name'] = $chinese_name;
            $data['sub_name'] = rtrim($sub_name, ')');
        } else {
            $data['chinese_name'] = $main_title;
            $data['sub_name'] = null;
        }

        // 提取描述和快照时间
        $item_data['description'] = trim($result_item->find('div.body', 0)->text());
        $item_data['snapshot_time'] = trim($result_item->find('span.info span.value', 1)->text());
        
        $item_data['data'] = $data;
        return $item_data;
    } catch (Exception $e) {
        error_log("Error extracting data: " . $e->getMessage());
        return null;
    }
}

// 主要搜索函数
function search_mcmod($search_term) {
   // require_once __DIR__ . '/vendor/autoload.php';  // 如果使用 Composer
    // 或
    require_once __DIR__ . '/simple_html_dom.php';  // 如果直接下载

    $url = 'https://search.mcmod.cn/s?key=' . urlencode($search_term);
    
    try {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: mcmod-api/1.0 (github.com/zkitefly/mcmod-api)'
            ]
        ];
        $context = stream_context_create($opts);
        $html = file_get_html($url, false, $context);
        
        if (!$html) {
            return [[], null, 0];
        }

        $search_result_list = $html->find('div.search-result-list', 0);
        if (!$search_result_list) {
            return [[], null, 0];
        }

        $results = [];
        foreach ($search_result_list->find('div.result-item') as $result_item) {
            $item_data = extract_item_data($result_item);
            if ($item_data) {
                $results[] = $item_data;
            }
        }

        list($best_result, $score) = get_best_result($results);
        return [$results, $best_result, $score];

    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        return [[], null, 0];
    }
}

// API 入口点
try {
    // 如果没有搜索参数，显示API使用说明
    if (!isset($_GET['key'])) {
        $api_docs = [
            'name' => 'MCMod 搜索 API',
            'version' => '1.0.0',
            'description' => '这是一个用于搜索 MCMod 百科的 API 接口',
            'base_url' => 'http://218.93.206.85:10369/search_api.php',
            'endpoints' => [
                [
                    'path' => '/',
                    'method' => 'GET',
                    'description' => '获取 API 使用说明'
                ],
                [
                    'path' => '/?key={search_term}',
                    'method' => 'GET',
                    'description' => '搜索模组',
                    'parameters' => [
                        'key' => [
                            'type' => 'string',
                            'required' => true,
                            'description' => '搜索关键词'
                        ]
                    ],
                    'example' => 'http://218.93.206.85:10369/search_api.php?key=IC2'
                ]
            ],
            'response_format' => [
                'success' => 'boolean - 请求是否成功',
                'search_term' => 'string - 搜索关键词',
                'timestamp' => 'string - 搜索时间',
                'total_results' => 'integer - 搜索结果总数',
                'best_result' => [
                    'score' => 'number - 结果评分',
                    'address' => 'string - 模组页面地址',
                    'title' => 'string - 模组标题',
                    'description' => 'string - 模组描述',
                    'snapshot_time' => 'string - 数据快照时间',
                    'data' => [
                        'mcmod_id' => 'string - 模组ID',
                        'abbr' => 'string|null - 模组缩写',
                        'chinese_name' => 'string - 模组中文名',
                        'sub_name' => 'string|null - 模组副标题/英文名'
                    ]
                ],
                'other_results' => 'array - 其他搜索结果，格式同 best_result'
            ],
            'example_response' => [
                'success' => true,
                'search_term' => 'IC2',
                'timestamp' => '2025-02-16 15:28:47',
                'total_results' => 30,
                'best_result' => [
                    'score' => 174.4,
                    'address' => 'https://www.mcmod.cn/class/2.html',
                    'title' => '[IC2] 工业时代2 (Industrial Craft 2)',
                    'description' => '工业时代2是一个...(省略)',
                    'snapshot_time' => '2025-02-12',
                    'data' => [
                        'mcmod_id' => '2',
                        'abbr' => 'IC2',
                        'chinese_name' => '工业时代2',
                        'sub_name' => 'Industrial Craft 2'
                    ]
                ],
                'other_results' => []
            ]
        ];

        echo json_encode($api_docs, 
            JSON_UNESCAPED_UNICODE | 
            JSON_PRETTY_PRINT | 
            JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    // 检查搜索参数
    if (!isset($_GET['key'])) {
        return_error('Missing search key parameter');
    }

    $search_term = trim($_GET['key']);
    if (empty($search_term)) {
        return_error('Empty search key');
    }

    // 执行搜索
    list($results, $best_result, $score) = search_mcmod($search_term);

    // 将 score 添加到 best_result 对象中，并确保是整数
    if ($best_result) {
        $best_result = array_merge(
            ['score' => round($score, 1)],  // 保留一位小数
            $best_result
        );
    }

    // 获取其他结果（排除最佳结果）并添加评分
    $other_results_with_scores = array_filter($results, function($result) use ($best_result) {
        if (!$best_result) return true;
        return $result['address'] !== $best_result['address'];
    });

    // 为每个结果计算评分
    $scored_others = array_map(function($result) {
        // 重用评分函数
        $score = score_result($result);
        return [
            'result' => $result,
            'score' => $score
        ];
    }, $other_results_with_scores);

    // 按分数排序
    usort($scored_others, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    // 限制数量并格式化
    $other_results = array_slice($scored_others, 0, 4);
    $other_results = array_map(function($item) {
        return array_merge(
            ['score' => round($item['score'], 1)],  // 保留一位小数
            $item['result']
        );
    }, $other_results);

    // 格式化时间戳
    $timestamp = date('Y-m-d H:i:s');  // 改为更易读的格式

    // 返回结果
    echo json_encode([
        'success' => true,
        'search_term' => $search_term,
        'timestamp' => $timestamp,
        'total_results' => count($results),
        'best_result' => $best_result,
        'other_results' => $other_results
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    return_error('Internal server error: ' . $e->getMessage(), 500);
} 