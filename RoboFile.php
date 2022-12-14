<?php

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

use Robo\Tasks;

/**
 * Robo Tasks.
 */
class RoboFile extends Tasks {

  /**
   * The path to custom modules.
   *
   * @var string
   */
  const CUSTOM_MODULES = __DIR__ . '/web/modules/custom';

  /**
   * The path to custom themes.
   *
   * @var string
   */
  const CUSTOM_THEMES = __DIR__ . '/web/themes/custom';

  /**
   * The theme machine name.
   *
   * @var string
   */
  const THEME_NAME = 'custom-theme';

  /**
   * Local Project init.
   *
   * @param string $site_name
   *   Site name.
   */
  public function localInit($site_name) {
    $this->say("Initializing new project...");
    $collection = $this->collectionBuilder();
    $collection
      ->taskComposerRequire()->dependency('mglaman/composer-drupal-lenient', '^1.0')
      ->taskComposerInstall()
      ->taskComposerRequire()->dependency('drupal/block_visibility_groups', '^1.4')
      ->ignorePlatformRequirements()
      ->noInteraction()
      ->taskFilesystemStack()->mkdir('config')
      ->addTaskList($this->prepareLocalFiles($site_name))
      ->addTask($this->installLocalDrupal());

    return $collection;
  }

  /**
   * Local Site update.
   */
  public function localUpdate() {
    $this->say("Local site update starting...");
    $collection = $this->collectionBuilder();
    $collection->taskComposerInstall()
      ->addTask($this->runDeploy());
    $this->say("local site Update Completed.");
    return $collection;
  }

  /**
   * Init theme.
   *
   * @param string $dir
   *   The directory to run the commands.
   *
   * @return \Robo\Collection\CollectionBuilder
   */
  public function themeInit($dir = '') {
    if (empty($dir)) {
      $dir = self::CUSTOM_THEMES . '/' . self::THEME_NAME;
    }
    $collection = $this->collectionBuilder();
    $collection->progressMessage('Init the theme...')
      ->taskNpmInstall()->dir($dir);

    return $collection;
  }

  /**
   * Build theme.
   *
   * @param string $dir
   *   The directory to run the commands.
   *
   * @return \Robo\Result
   *   The result of the collection of tasks.
   */
  public function themeBuild($dir = '') {
    if (empty($dir)) {
      $dir = self::CUSTOM_THEMES . '/' . self::THEME_NAME;
    }
    $collection = $this->collectionBuilder();
    $collection->progressMessage('Building the theme...')
      ->taskExec('npm run production')->dir($dir);

    return $collection;
  }

  /**
   * Update theme styles.
   */
  public function themeSass($dir = '') {
    if (empty($dir)) {
      $dir = self::CUSTOM_THEMES . '/' . self::THEME_NAME;
    }
    $collection = $this->collectionBuilder();
    $collection->progressMessage('Compiling theme styles...')
      ->taskExec('npm run watch')->dir($dir);
  }

  /**
   * Runs Codesniffer.
   */
  public function phpcs() {
    return $this->taskExec('vendor/bin/phpcs')
      ->arg('-ns')
      ->printOutput(TRUE)
      ->run();
  }

  /**
   * Runs phpstan.
   */
  public function analyse() {
    return $this->taskExec('vendor/bin/phpstan')
      ->arg('analyse')
      ->printOutput(TRUE)
      ->run();
  }

  /**
   * CI JOBS.
   */

  /**
   * Command to build the environment.
   *
   * @return \Robo\Result
   *   The result of the collection of tasks.
   */
  public function ciBuild() {
    $collection = $this->collectionBuilder();
    $collection->addTaskList($this->copyConfigurationFiles());
    return $collection->run();
  }

  /**
   * Command to run unit tests.
   *
   * @return \Robo\Result
   *   The result of the collection of tasks.
   */
  public function ciUnitTests() {
    $collection = $this->collectionBuilder();
    $collection->addTask($this->installDrupal());
    $collection->addTaskList($this->runUnitTests());
    return $collection->run();
  }

  /**
   * Serve Drupal.
   *
   * @return \Robo\Result
   *   The result tof the collection of tasks.
   */
  public function ciServeDrupal() {
    $collection = $this->collectionBuilder();
    $collection->taskExec('vendor/bin/drush sqlc < behat-db.sql');
    $collection->addTask($this->runDeploy());
    $collection->addTaskList($this->runDisableLdap());
    $collection->addTaskList($this->runServeDrupal());
    return $collection->run();
  }

  /**
   * Command to run Chrome headless.
   *
   * @return \Robo\Result
   *   The result tof the task
   */
  public function ciChromeHeadless() {
    return $this->taskExec('google-chrome-unstable --no-sandbox --disable-gpu --headless --window-size=1200,900 --remote-debugging-address=0.0.0.0 --remote-debugging-port=9222 --disable-extensions --disable-dev-shm-usage')->run();
  }

  /**
   * Prepares code sniffer.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  public function ciPrepareCodeSniffer() {
    $collection = $this->collectionBuilder();
    $collection->taskExec('vendor/bin/phpcs --config-set installed_paths vendor/drupal/coder/coder_sniffer');
    $collection->taskExec('vendor/bin/phpcs -i');
    return $collection->run();
  }

  /**
   * Command to run behat tests.
   *
   * @return \Robo\Result
   *   The result tof the collection of tasks.
   */
  public function ciBehatTests() {
    $collection = $this->collectionBuilder();
    $collection->addTaskList($this->runBehatTests());
    return $collection->run();
  }

  /**
   * Runs Behat tests.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runBehatTests() {
    $force = TRUE;
    $tasks = [];
    $tasks[] = $this->taskFilesystemStack()
      ->mkdir('screenshots');
    $tasks[] = $this->taskFilesystemStack()
      ->copy('.bitbucket/config/behat.yml', 'tests/behat.yml', $force);
    $tasks[] = $this->taskExec('sleep 30s');
    $tasks[] = $this->taskExec('vendor/bin/behat --verbose --colors -c tests/behat.yml');
    return $tasks;
  }

  /**
   * Run unit tests.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runUnitTests() {
    $force = TRUE;
    $tasks = [];
    $tasks[] = $this->taskFilesystemStack()
      ->copy('.bitbucket/config/phpunit.xml', 'web/core/phpunit.xml', $force);
    $tasks[] = $this->taskExecStack()
      ->dir('web')
      ->exec('XDEBUG_MODE=coverage ../vendor/bin/phpunit -c core --debug --coverage-clover ../build/logs/clover.xml --verbose modules/custom');
    return $tasks;
  }

  /**
   * Install local Drupal.
   *
   * @return \Robo\Task\Base\Exec
   *   A task to install Drupal.
   */
  protected function installLocalDrupal() {
    $LOCAL_MYSQL_USER = getenv('DRUPAL_DB_USER');
    $LOCAL_MYSQL_PASSWORD = getenv('DRUPAL_DB_PASS');
    $LOCAL_MYSQL_DATABASE = getenv('DRUPAL_DB_NAME');
    $LOCAL_MYSQL_PORT = getenv('DRUPAL_DB_PORT');
    $LOCAL_CONFIG_DIR = getenv('DRUPAL_CONFIG_DIR');

    $task = $this->drush()
      ->args([
        'site-install',
        '--account-name=admin',
        '--account-pass=admin',
        "--config-dir=$LOCAL_CONFIG_DIR",
        "--db-url=mysql://$LOCAL_MYSQL_USER:$LOCAL_MYSQL_PASSWORD@database:$LOCAL_MYSQL_PORT/$LOCAL_MYSQL_DATABASE"
      ])
      ->option('verbose')
      ->option('yes');
    return $task;
  }

  /**
   * Install Drupal.
   *
   * @return \Robo\Task\Base\Exec
   *   A task to install Drupal.
   */
  protected function installDrupal() {
    $task = $this->drush()
      ->args('site-install')
      ->option('verbose')
      ->option('yes');
    return $task;
  }

  /**
   * Serves Drupal.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runServeDrupal() {
    $tasks = [];
    $tasks[] = $this->taskExec('chown -R www-data:www-data ' . getenv('BITBUCKET_CLONE_DIR'));
    $tasks[] = $this->taskExec('ln -sf ' . getenv('BITBUCKET_CLONE_DIR') . '/web /var/www/html');
    $tasks[] = $this->taskExec('service apache2 start');
    return $tasks;
  }

  /**
   * Updates the database.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runUpdateDatabase() {
    $tasks = [];
    $tasks[] = $this->drush()
      ->args('updatedb')
      ->option('yes')
      ->option('verbose');
    $tasks[] = $this->drush()
      ->args('config:import')
      ->option('yes')
      ->option('verbose');
    $tasks[] = $this->drush()->args('cache:rebuild')->option('verbose');
    $tasks[] = $this->drush()->args('st');
    return $tasks;
  }

  /**
   * Deploy site.
   *
   * @return \Robo\Task\Base\Exec
   *   An array of tasks.
   */
  protected function runDeploy() {
    $task = $this->drush()
      ->args('deploy');
    return $task;
  }

  /**
   * Disable LDAP.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function runDisableLdap() {
    $task[] = $this->drush()
      ->args([
        'pmu',
        'ldap_authentication',
        'ldap_authorization',
        'ldap_query',
        'ldap_servers',
        'ldap_user'
      ])
      ->option('yes');
    $tasks[] = $this->drush()->args('cache:rebuild')->option('verbose');
    return $task;
  }

  /**
   * Copies configuration files.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function copyConfigurationFiles() {
    $force = TRUE;
    $tasks = [];
    $tasks[] = $this->taskFilesystemStack()
      ->remove('web/sites/default/settings.php')
      ->remove('web/sites/default/files')
      ->copy('.bitbucket/config/settings.php', 'web/sites/default/settings.php', $force)
      ->copy('.bitbucket/config/.env', '.env', $force);
    return $tasks;
  }

  /**
   * Prepares local files and folders.
   *
   * @param string $site_name
   *   Site name.
   *
   * @return \Robo\Task\Base\Exec[]
   *   An array of tasks.
   */
  protected function prepareLocalFiles($site_name) {
    $tasks = [];
    $tasks[] = $this->taskFilesystemStack()
      ->mkdir('config')
      ->rename('drush/sites/drupal10_starter.site.yml', 'drush/sites/' . $site_name . '.site.yml');
    // Renaming files and replacing names.
    $tasks[] =  $this->taskExec("sed -i 's/drupal10_starter/" . $site_name . "/g' drush/sites/" . $site_name . ".site.yml");
    $tasks[] =  $this->taskExec("sed -i 's/drupal10_starter/" . $site_name . "/g' tests/behat.yml");
    $tasks[] =  $this->taskExec("sed -i 's/drupal10_starter/" . $site_name . "/g' .bitbucket/config/behat.yml");
    $tasks[] =  $this->taskExec("sed -i 's/drupal10-starter/" . str_replace('_', '-', $site_name) . "/g' drush/sites/" . $site_name . ".site.yml");
    $tasks[] =  $this->taskExec("sed -i 's/drupal10-starter/" . str_replace('_', '-', $site_name) . "/g' bitbucket-pipelines.yml");
    return $tasks;
  }

  /**
   * Return drush with default arguments.
   *
   * @return \Robo\Task\Base\Exec
   *   A drush exec command.
   */
  protected function drush() {
    return $this->taskExec('vendor/bin/drush');
  }

}
