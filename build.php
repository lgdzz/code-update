<?php

/**
 * @title 代码增量更新
 * @version v1.0
 */

require './vendor/autoload.php';
require './config.php';

use PhpZip\ZipFile;

//更新包版本号
$version_code = $argv[1] ?? '';

class Build
{
    const VERSION = 'version';

    protected $version_code = '';
    protected $code_path = '';
    protected $local_file_list = [];
    protected $filter_file_list = [];

    public function __construct(array $config = [])
    {
        $this->version_code = $config['version_code'] ?? '';
        $this->code_path = $config['code_path'] ?? '';
        $this->filter_file_list = $config['filter_file'] ?? [];
    }

    //读取本地文件列表
    public function getLocalFileList($dir)
    {
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (is_dir($dir . '/' . $file) && !in_array($file, $this->filter_file_list)) {
                        $this->getLocalFileList($dir . '/' . $file . '/');
                    } else {
                        if (!in_array($file, $this->filter_file_list)) {
                            $path = str_replace('//', '/', $dir . '/' . $file);
                            $this->local_file_list[] = md5_file($path) . '@@@' . $path;
                        }
                    }
                }
                closedir($dh);
            }
        }
    }

    //压缩更新包
    public function makeUpdatePackage()
    {
        if (!$this->version_code) {
            $this->output('请输入制作版本号，如：1.0');
            return;
        } elseif (!$this->code_path) {
            $this->output('请在config.php中配置代码绝对路径，如：/User/wxapp');
            return;
        } elseif (!is_dir($this->code_path)) {
            $this->output('代码路径无效，请在config.php中配置code_path');
            return;
        }
        $this->output('1.开始制作更新包');
        $this->output('2.读取本地文件列表');
        $this->getLocalFileList($this->code_path);
        $this->output('3.匹配版本库');
        $log_file_list = is_file($this->getRepositoryFilePath()) ? json_decode(file_get_contents($this->getRepositoryFilePath()), true) : [];
        $update_files = array_diff($this->local_file_list, $log_file_list);
        $update_files_num = count($update_files);
        $already_handle_num = 0;
        $log = [];
        $zipFile = new ZipFile();
        try {
            if (empty($update_files)) {
                $this->output('No code updates');
                return;
            }
            $this->output('4.添加更新文件');
            foreach ($update_files as $item) {
                list($md5, $path) = explode('@@@', $item);
                $path_ = str_replace($this->code_path, '/' . $this->getUpdateFileName(), $path);
                $zipFile->addFile($path, $path_);
                $log[] = $md5 . '@@@' . $path_;
                $already_handle_num++;
                $this->output('4.已添加文件数：' . $already_handle_num . '，剩余文件数：' . ($update_files_num - $already_handle_num));
            }
            $date = date('Y-m-d');
            $log = "更新时间：{$date}；更新文件数量：{$update_files_num}；\n" . implode("\n", $log);
            $zipFile->addFromString($this->getUpdateFileName() . '.log', $log);
            $this->output('5.开始压缩');
            $zipFile->saveAsFile('./' . $this->getUpdateFilePath())->close();
            $this->output('6.制作完成');
            $this->output(getcwd() . '/' . $this->getUpdateFilePath());
            file_put_contents($this->getRepositoryFilePath(), json_encode($this->local_file_list, JSON_UNESCAPED_UNICODE));
        } catch (\PhpZip\Exception\ZipException $e) {
            $this->output($e->getMessage());
        } finally {
            $zipFile->close();
        }
    }

    private function getUpdateFilePath()
    {
        if (!is_dir('package')) {
            mkdir('package');
        }
        return 'package/' . $this->getUpdateFileName() . '.zip';
    }

    private function getUpdateFileName()
    {
        return 'update' . $this->version_code . '-' . date('Ymd');
    }

    private function getRepositoryFilePath()
    {
        return './' . self::VERSION;
    }

    private function output($msg)
    {
        echo $msg . "\n";
    }
}

set_time_limit(0);
$package = new Build([
  'version_code' => $version_code,
  'code_path'    => $config['code_path'],
  'filter_file'  => $config['filter_file']
]);
$package->makeUpdatePackage();