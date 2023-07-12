<?php

/**
 * Auto Include System Plugin
 *
 * @author Brandon J. Yaniz (joomla@adept.travel)
 * @copyright 2022 The Adept Traveler, Inc., All Rights Reserved.
 * @license BSD 2-Clause; See LICENSE.txt
 */
defined('_JEXEC') or die();

class PlgSystemAutoInclude extends \Joomla\CMS\Plugin\CMSPlugin
{
  protected $app;
  protected $assets;
  protected $files;
  protected $log;
  protected $path;

  /**
   * Initilize Plugin
   *
   * @param   &$subject
   * @param   $config
   */
  public function __construct(&$subject, $config)
  {
    // Calling the parent Constructor
    parent::__construct($subject, $config);

    $this->app = \Joomla\CMS\Factory::getApplication();
    $this->log = [];

    $this->files = (object)[
      'css' => [],
      'js' => []
    ];


    /** @var Joomla\CMS\Document\ErrorDocument $this */
  }

  public function onBeforeCompileHead()
  {
    if (
      $this->app->isClient('site')
      && $this->app->getDocument() instanceof \Joomla\CMS\Document\HtmlDocument
    ) {

      $this->assets = $this->app->getDocument()->getWebAssetManager();
      $this->path = JPATH_BASE . '/templates/' . $this->app->getDocument()->template . '/';

      $path = \Joomla\CMS\Uri\Uri::getInstance()->getPath();

      if ($path == '/') {
        $path = '/home';
      }

      // Google Fonts
      if ($this->params->get('google_font', 0) && !empty($url = $this->params->get('google_font_url', ''))) {
        $this->app->getDocument()->getPreloadManager()->preconnect('https://fonts.googleapis.com/', ['crossorigin' => 'anonymous']);
        $this->app->getDocument()->getPreloadManager()->preconnect('https://fonts.gstatic.com/', ['crossorigin' => 'anonymous']);
        $this->app->getDocument()->getPreloadManager()->preload($url, ['as' => 'style', 'crossorigin' => 'anonymous']);

        $this->assets->registerAndUseStyle(
          'googlefonts',
          $url,
          [], //['version' => 'auto'],
          [
            'rel' => 'stylesheet'
          ]
        );

        $this->log[$url] = true;
      }

      // Font Awesome
      if ($this->params->get('fontawesome', 0)) {
        switch ($this->params->get('fontawesome_location', 'joomla')) {
          case 'local':
            $url = 'media/plg_system_autoinclude/css/fa.min.css';
            break;
          case 'cdn':
            $url = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css';
            $this->app->getDocument()->getPreloadManager()->preload($url, ['as' => 'style', 'crossorigin' => 'anonymous']);
            break;
          case 'joomla':
          default:
            //$url = 'system/joomla-fontawesome.min.css';
            break;
        }

        if (!empty($url)) {
          $this->assets->registerAndUseStyle(
            'fontawesome',
            $url,
            [], //['version' => 'auto'],
            [
              'rel' => 'stylesheet'
            ]
          );

          //$this->assets->disableStyle('awesomplete');
        }

        $this->log[$url] = true;
      }

      // Core Files
      if ($this->params->get('css_include', 0)) {

        // Include core CSS files
        foreach ($this->getFiles($this->path . 'css', 'css') as $abs) {
          if (substr($abs, -10) == 'editor' && $this->params->get(css_include_editor, 0)) {
            continue;
          }

          $this->addAsset($abs);
        }
      }

      if ($this->params->get('js_include', 0)) {
        // Include core JavaScript files
        foreach ($this->getFiles($this->path . 'js', 'js') as $abs) {
          $this->addAsset($abs);
        }
      }

      $search[] = 'component/' . $this->app->input->getCmd('option', '');
      $search[] = $search[0] . '/' . $this->app->input->getCmd('view', '');

      if (!empty($layout = $this->app->input->getCmd('layout', ''))) {
        $search[] = $search[1] . '/' . str_replace($this->app->getTemplate(), '', $layout);
      }

      if (!empty($task = $this->app->input->getCmd('task', ''))) {
        $search[] = $search[1] . '/' . $task;
      }

      if (!empty($itemid = $this->app->input->getCmd('Itemid', ''))) {
        $search[] = 'itemid/' . $itemid;
      }

      $path = substr($path, 1);
      $parts = explode('/', $path);

      if (count($parts) > 0) {
        $p = '';

        for ($i = 0; $i < count($parts); $i++) {
          $p .= '/' . $parts[$i];
          $search[] = 'path' . $p;
        }
      }


      // Modules
      //die('<pre>' . print_r(\Joomla\CMS\Helper\ModuleHelper::getModuleList(), true));
      foreach (\Joomla\CMS\Helper\ModuleHelper::getModuleList() as $module) {

        // By module type
        $s = 'module/' . $module->module;

        if (!in_array($s, $search)) {
          $search[] = $s;
        }

        // By ID
        $s = 'module/id/' . $module->id;

        if (!in_array($s, $search)) {
          $search[] = $s;
        }

        // Position
        $s = 'module/position/' . $module->position;

        if (!in_array($s, $search)) {
          $search[] = $s;
        }


        // Module type in position
        $s = 'module/position/' . $module->position . '/' . $module->module;

        if (!in_array($s, $search)) {
          $search[] = $s;
        }

        if ($module->module == 'mod_menu') {
          $search[] = 'module/' . $module->module . '/' . json_decode($module->params)->menutype;
        }
      }

      // Add assets to queue
      foreach ($search as $s) {

        if ($this->params->get('css_include', 0)) {
          $this->addAsset($this->path . 'css/' . $s . '.css');
        }
        if ($this->params->get('js_include', 0)) {
          $this->addAsset($this->path . 'js/' . $s . '.js');
        }
      }

      if ($this->app->getDocument() instanceof \Joomla\CMS\Document\ErrorDocument) {
        $errorCSS = $this->path . 'css/component/error.css';

        if (file_exists($errorCSS)) {
          $this->addAsset($errorCSS);
        }
      }
    }
  }

  public function onAfterRender()
  {
    if (
      $this->app->isClient('site')
      && $this->app->getDocument() instanceof \Joomla\CMS\Document\HtmlDocument
      && $this->params->get('mode', 'production') == 'development'
    ) {
      ksort($this->log);

      echo "<!-- Auto Include Log\n";

      foreach ($this->log as $k => $v) {
        echo ($v) ? '  Adding ' : 'Ignoring ';
        echo $k . "\n";
      }

      echo "-->\n\n";
    }
  }

  protected function addAsset(string $abs)
  {
    $rel = str_replace(JPATH_BASE . DIRECTORY_SEPARATOR, '', $abs);
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
    $ext = substr($abs, strrpos($abs, '.') + 1);
    $name = str_replace($this->path . $ext . DIRECTORY_SEPARATOR, '', $abs);

    if (substr($name, 2, 1) == '-') {
      $name = substr($name, 3);
    }

    $name = 'template.' . str_replace(DIRECTORY_SEPARATOR, '.', substr($name, 0, strrpos($name, '.')));

    if ($this->log[$rel] = file_exists($abs)) {
      if ($ext == 'css') {
        $this->files->css[] = (object)['name' => $name, 'rel' => $rel];
        $this->assets->registerAndUseStyle($name, $rel, [], [], []);
      } else if ($ext == 'js') {
        $this->files->js[] = (object)['name' => $name, 'rel' => $rel];
        $this->assets->registerAndUseScript($name, $rel, [], ['defer' => true], []);
      }
    }
  }

  protected function getFiles(string $path, string $ext, bool $recursive = false): array
  {

    $path .= DIRECTORY_SEPARATOR;
    $files = [];

    if (file_exists($path)) {
      foreach (scandir($path) as $fs) {
        if ($fs == '.' || $fs == '..') {
          continue;
        }

        if ($recursive && is_dir($path . $fs)) {
          array_merge($files, $this->getFiles($path . $fs, $ext));
        }

        if (substr($fs, strlen($fs) - strlen($ext)) == $ext) {
          $files[] = $path . $fs;
        }
      }
    }

    return $files;
  }

  /**
   * Get the directory to cache asset files in, creates if does not exist.
   *
   * @return string absolute cache directory
   */
  protected function getCachePath(string $path): string
  {
    $cache = '';

    if (file_exists(JPATH_CACHE)) {

      $cache = JPATH_CACHE . '/plg_system_autoinclude/' . $path;

      if (!file_exists($cache)) {
        if (!mkdir($cache, 0755, true)) {
          throw new Exception('Can\'t create cache directory.');
        }
      }
    } else {
      throw new Exception('System cache directory not found.');
    }

    return $cache;
  }
}
