<?php
/**
 * 自动获取最新版本信息 - 增强版
 * 
 * 功能:
 * - 自动检测更新包目录
 * - 支持多种路径格式 (相对/绝对)
 * - 详细的错误诊断信息
 * - 支持手动指定版本号（用于测试）
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');  // 允许跨域请求

// ==================== 配置区 ====================
// 方法1: 绝对路径 (Linux)
// $dir = '/var/www/genxin/downloads/';

// 方法2: Windows 绝对路径
// $dir = 'C:/xampp/htdocs/genxin/downloads/';

// 方法3: 相对路径 (相对于此PHP文件的位置) ✅ 推荐
$dir = __DIR__ . '/downloads/';

// 方法4: 如果上述都不行，使用当前目录下的 updates 文件夹
// $dir = __DIR__ . '/updates/';
// ===============================================

// 调试模式（生产环境请设为 false）
$debug_mode = true;

function send_error($message, $debug_info = '') {
    global $debug_mode;
    $response = [
        'success' => false,
        'error' => $message
    ];
    
    if ($debug_mode && $debug_info) {
        $response['debug'] = $debug_info;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

function send_success($version, $filename, $download_url, $hash = '') {
    echo json_encode([
        'success'       => true,
        'version'       => $version,
        'download_url'  => $download_url,
        'file_hash'     => $hash,
        'filename'      => $filename,
        'release_notes' => '',  // 可从文件读取
        'server_time'   => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== 主逻辑 ====================

// 检查目录是否存在
if (!is_dir($dir)) {
    send_error(
        '更新目录不存在',
        [
            'configured_path' => $dir,
            'current_dir'     => __DIR__,
            'suggestion'      => '请在服务器上创建该目录，或修改 $dir 配置'
        ]
    );
}

// 检查目录是否可读
if (!is_readable($dir)) {
    send_error(
        '更新目录不可读',
        [
            'path' => $dir,
            'suggestion' => '请检查目录权限 (chmod 755 或修改所有者)'
        ]
    );
}

// 扫描 .zip 文件
$files = glob($dir . '*.zip');
$versions = [];

foreach ($files as $file) {
    $filename = basename($file);
    
    // 匹配版本号: EDIST-v1.2.3.zip 或 v1.2.3.zip
    if (preg_match('/v?(\d+\.\d+\.\d+)/', $filename, $matches)) {
        $version = $matches[1];
        $versions[$version] = $filename;
        
        if ($debug_mode) {
            error_log("Found version: $version -> $filename");
        }
    }
}

// 如果没有找到版本文件
if (empty($versions)) {
    send_error(
        '未找到有效版本文件',
        [
            'directory'  => $dir,
            'files_found' => count($files),
            'expected_format' => 'EDIST-vX.Y.Z.zip 或 vX.Y.Z.zip',
            'suggestion'  => '请上传符合命名规范的 zip 文件到更新目录'
        ]
    );
}

// 排序并获取最新版本
uksort($versions, 'version_compare');
$latest_version = array_key_last($versions);
$latest_filename = $versions[$latest_version];

// 读取 SHA256 校验值（可选）
$hash_file = $dir . $latest_filename . '.sha256';
$file_hash = '';
if (file_exists($hash_file) && is_readable($hash_file)) {
    $file_hash = trim(file_get_contents($hash_file));
} elseif ($debug_mode) {
    // 自动生成校验值（如果文件存在但没生成 hash）
    $full_path = $dir . $latest_filename;
    if (file_exists($full_path)) {
        $file_hash = hash_file('sha256', $full_path);
        error_log("Auto-generated SHA256: $file_hash");
        
        // 可选：保存到文件以便下次使用
        // file_put_contents($hash_file, $file_hash);
    }
}

// 构建下载 URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$request_uri = dirname($_SERVER['SCRIPT_NAME']);

// 清理 URL 路径
$web_path = rtrim(str_replace('\\', '/', $request_uri), '/') . '/downloads/';
$download_url = "$protocol://$host$web_path" . rawurlencode($latest_filename);

// 输出成功结果
send_success($latest_version, $latest_filename, $download_url, $file_hash);

?>
