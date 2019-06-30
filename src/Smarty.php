<?php

declare (strict_types = 1);

namespace think\view\driver;

use think\App;
use think\template\exception\TemplateNotFoundException;
use Smarty as BasicSmarty;

class Smarty
{
    private $template;
    private $app;
    protected $config = [
        // 默认模板渲染规则 1 解析为小写+下划线 2 全部转换小写
        'auto_rule'   => 1,
        // 视图基础目录（集中式）
        'view_base'   => '',
        // 模板起始路径
        'view_path'   => '',
        // 模板文件后缀
        'view_suffix' => 'html',
        // 模板文件名分隔符
        'view_depr'   => DIRECTORY_SEPARATOR,
        // 模板引擎普通标签开始标记
        'tpl_begin'    => '{',
        // 模板引擎普通标签结束标记
        'tpl_end'      => '}',
        // 是否开启模板编译缓存,设为false则每次都会重新编译
        'tpl_cache'   => true,
        'cache_path' =>'',
        'compile_path'=>'',
        //模板目录
        'tpl_dir' => '',
        // 是否处于调试模式
        'debug' => false
    ];

    public function __construct(App $app, array $config = [])
    {
        $this->app = $app;


        $this->config = array_merge($this->config, (array) $config);

        if (empty($this->config['view_path'])) {
            $this->config['view_path'] = $app->getAppPath() . 'view' . DIRECTORY_SEPARATOR;
        }

        if (empty($this->config['cache_path'])) {
            $this->config['cache_path'] = $app->getRuntimePath() . 'temp' . DIRECTORY_SEPARATOR;
        }

        $this->config['debug'] = $this->app->isDebug();
        $this->config['tpl_dir'] = '';
        $this->config['cache_path'] = $this->app->getRuntimePath() . 'cache' . DIRECTORY_SEPARATOR;
        $this->config['compile_path'] = $this->app->getRuntimePath() . 'cache_c' . DIRECTORY_SEPARATOR;
        $this->template = new BasicSmarty();
        $this->template->setLeftDelimiter($this->config['tpl_begin']);
        $this->template->setRightDelimiter($this->config['tpl_end']);
        $this->template->setCaching(!$this->config['debug']);
        $this->template->setForceCompile(!$this->config['debug']);//是否强制编译
        $this->template->setTemplateDir($this->config['view_base']);//设置模板目录
        $this->template->setCacheDir($this->config['cache_path']);//设置模板缓存目录
        $this->template->setCompileDir($this->config['compile_path']);//设置模板编译目录
        $this->template->setMergeCompiledIncludes(true);//合并编译导入
    }

    /**
     * 检测是否存在模板文件
     * @access public
     * @param  string $template 模板文件或者模板规则
     * @return bool
     */
    public function exists(string $template): bool
    {
        if ('' == pathinfo($template, PATHINFO_EXTENSION)) {
            // 获取模板文件名
            $template = $this->parseTemplate($template);
        }

        return is_file($template);
    }

    /**
     * 渲染模板文件
     * @access public
     * @param  string    $template 模板文件
     * @param  array     $data 模板变量
     * @return void
     */
    public function fetch(string $template, array $data = []): void
    {
        if ('' == pathinfo($template, PATHINFO_EXTENSION)) {
            // 获取模板文件名
            $template = $this->parseTemplate($template);
        }

        // 模板不存在 抛出异常
        if (!is_file($template)) {
            throw new TemplateNotFoundException('template not exists:' . $template, $template);
        }

        // 记录视图信息
        $this->app->isDebug() && $this->app['log']
            ->record('[ VIEW ] ' . $template . ' [ ' . var_export(array_keys($data), true) . ' ]');

        // 赋值模板变量
        !empty($template) && $this->template->assign($data);
        echo $this->template->fetch($template);
    }

    /**
     * 渲染模板内容
     * @access public
     * @param  string    $template 模板内容
     * @param  array     $data 模板变量
     * @return void
     */
    public function display(string $template, array $data = [], $config = []): void
    {
        $this->fetch($template, $data, $config);
    }

    /**
     * 自动定位模板文件
     * @access private
     * @param  string $template 模板文件规则
     * @return string
     */
    private function parseTemplate(string $template): string
    {
        // 分析模板文件规则
        $request = $this->app['request'];

        // 获取视图根目录
        if (strpos($template, '@')) {
            // 跨模块调用
            list($app, $template) = explode('@', $template);
        }

        if ($this->config['view_base']) {
            // 基础视图目录
            $app  = isset($app) ? $app : $request->app();
            $path = $this->config['view_base'] . ($app ? $app . DIRECTORY_SEPARATOR : '');
        } else {
            $path = isset($app) ? $this->app->getBasePath() . $app . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR : $this->config['view_path'];
        }

        $depr = $this->config['view_depr'];

        if (0 !== strpos($template, '/')) {
            $template   = str_replace(['/', ':'], $depr, $template);
            $controller = App::parseName($request->controller());
            if ($controller) {
                if ('' == $template) {
                    // 如果模板文件名为空 按照默认规则定位
                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . (1 == $this->config['auto_rule'] ? App::parseName($request->action(true)) : $request->action());
                } elseif (false === strpos($template, $depr)) {
                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . $template;
                }
            }
        } else {
            $template = str_replace(['/', ':'], $depr, substr($template, 1));
        }

        return $path . ltrim($template, '/') . '.' . ltrim($this->config['view_suffix'], '.');
    }

    /**
     * 配置模板引擎
     * @access private
     * @param  array  $config 参数
     * @return void
     */
    public function config(array $config): void
    {
        $this->template->config($config);
        $this->config = array_merge($this->config, $config);
    }

    public function __call($method, $params)
    {
        return call_user_func_array([$this->template, $method], $params);
    }

}