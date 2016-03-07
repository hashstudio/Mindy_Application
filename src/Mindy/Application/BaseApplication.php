<?php
/**
 *
 *
 * All rights reserved.
 *
 * @author Falaleev Maxim
 * @email max@studio107.ru
 * @version 1.0
 * @company Studio107
 * @site http://studio107.ru
 * @date 09/06/14.06.2014 17:22
 */

namespace Mindy\Application;

use Mindy\Base\Interfaces\IApplicationComponent;
use Mindy\Base\Mindy;
use Mindy\Base\Module;
use Mindy\Di\ServiceLocator;
use Mindy\Exception\Exception;
use Mindy\Exception\HttpException;
use Mindy\Helper\Alias;
use Mindy\Helper\Collection;
use Mindy\Helper\Creator;
use Mindy\Helper\Traits\BehaviorAccessors;
use Mindy\Helper\Traits\Configurator;
use Mindy\Locale\Translate;

/**
 * CApplication class file.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 *
 * CApplication is the base class for all application classes.
 *
 * An application serves as the global context that the user request
 * is being processed. It manages a set of application components that
 * provide specific functionalities to the whole application.
 *
 * The core application components provided by CApplication are the following:
 * <ul>
 * <li>{@link getErrorHandler errorHandler}: handles PHP errors and
 *   uncaught exceptions. This application component is dynamically loaded when needed.</li>
 * <li>{@link getSecurityManager securityManager}: provides security-related
 *   services, such as hashing, encryption. This application component is dynamically
 *   loaded when needed.</li>
 * <li>{@link getStatePersister statePersister}: provides global state
 *   persistence method. This application component is dynamically loaded when needed.</li>
 * <li>{@link getCache cache}: provides caching feature. This application component is
 *   disabled by default.</li>
 * <li>{@link getMessages messages}: provides the message source for translating
 *   application messages. This application component is dynamically loaded when needed.</li>
 * <li>{@link getCoreMessages coreMessages}: provides the message source for translating
 *   Yii framework messages. This application component is dynamically loaded when needed.</li>
 * <li>{@link getUrlManager urlManager}: provides URL construction as well as parsing functionality.
 *   This application component is dynamically loaded when needed.</li>
 * <li>{@link getRequest request}: represents the current HTTP request by encapsulating
 *   the $_SERVER variable and managing cookies sent from and sent to the user.
 *   This application component is dynamically loaded when needed.</li>
 * <li>{@link getFormat format}: provides a set of commonly used data formatting methods.
 *   This application component is dynamically loaded when needed.</li>
 * </ul>
 *
 * CApplication will undergo the following lifecycles when processing a user request:
 * <ol>
 * <li>load application configuration;</li>
 * <li>set up error handling;</li>
 * <li>load static application components;</li>
 * <li>{@link onBeginRequest}: preprocess the user request;</li>
 * <li>{@link processRequest}: process the user request;</li>
 * <li>{@link onEndRequest}: postprocess the user request;</li>
 * </ol>
 *
 * Starting from lifecycle 3, if a PHP error or an uncaught exception occurs,
 * the application will switch to its error handling logic and jump to step 6 afterwards.
 *
 * @property string $id The unique identifier for the application.
 * @property string $basePath The root directory of the application. Defaults to 'protected'.
 * @property string $runtimePath The directory that stores runtime files. Defaults to 'protected/runtime'.
 * @property string $extensionPath The directory that contains all extensions. Defaults to the 'extensions' directory under 'protected'.
 * @property string $timeZone The time zone used by this application.
 * @property \Mindy\Query\Connection $db The database connection.
 * @property \Mindy\Base\ErrorHandler $errorHandler The error handler application component.
 * @property \Mindy\Security\SecurityManager $securityManager The security manager application component.
 * @property \Mindy\Base\StatePersister $statePersister The state persister application component.
 * @property \Mindy\Cache\Cache $cache The cache application component. Null if the component is not enabled.
 * @property \Mindy\Mail\Mailer $mail The mail application component. Null if the component is not enabled.
 * @property \Mindy\Locale\Translate $translate The application translate component.
 * @property \Mindy\Http\Request $request The request component.
 * @property \Mindy\Template\Renderer $template The template engine component.
 * @property \Mindy\Router\UrlManager $urlManager The URL manager component.
 * @property \Mindy\Controller\BaseController $controller The currently active controller. Null is returned in this base class.
 * @property string $baseUrl The relative URL for the application.
 * @property string $homeUrl The homepage URL.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.base
 * @since 1.0
 */
abstract class BaseApplication
{
    use Configurator, BehaviorAccessors;

    /**
     * @var array
     */
    public $managers = [];
    /**
     * @var array
     */
    public $admins = [];
    /**
     * @var string the application name. Defaults to 'My Application'.
     */
    public $name = 'My Application';
    /**
     * @var string the class used to handle errors
     */
    public $errorHandlerConfig = [
        'class' => '\Mindy\Base\ErrorHandler'
    ];
    /**
     * @var array
     */
    public $locale = [];
    /**
     * @var array the IDs of the application components that should be preloaded.
     */
    public $preload = [];

    private $_id;
    private $_basePath;
    private $_modulePath;
    private $_runtimePath;
    private $_globalState;
    private $_stateChanged;
    private $_ended = false;
    private $_homeUrl;
    private $_params;
    private $_modules = [];
    private $_moduleConfig = [];
    private $_componentConfig = [];

    /**
     * @var \Mindy\Di\ServiceLocator
     */
    private $_locator;

    /**
     * Processes the request.
     * This is the place where the actual request processing work is done.
     * Derived classes should override this method.
     */
    abstract public function processRequest();

    /**
     * Constructor.
     * @param mixed $config application configuration.
     * If a string, it is treated as the path of the file that contains the configuration;
     * If an array, it is the actual configuration information.
     * Please make sure you specify the {@link getBasePath basePath} property in the configuration,
     * which should point to the directory containing all application logic, template and data.
     * If not, the directory will be defaulted to 'protected'.
     * @throws \Mindy\Exception\Exception
     */
    public function __construct($config = null)
    {
        Mindy::setApplication($this);

        // set basePath at early as possible to avoid trouble
        if (is_string($config)) {
            $config = require($config);
        }

        if (isset($config['basePath'])) {
            $this->setBasePath($config['basePath']);
            unset($config['basePath']);
        } else {
            $this->setBasePath('protected');
        }

        Alias::set('App', $this->getBasePath());
        Alias::set('Modules', $this->getBasePath() . DIRECTORY_SEPARATOR . 'Modules');

        if (isset($config['webPath'])) {
            $path = realpath($config['webPath']);
            if (!is_dir($path)) {
                throw new Exception("Incorrent web path " . $config['webPath']);
            }
            Alias::set('www', $path);
            unset($config['webPath']);
        } else {
            Alias::set('www', realpath(dirname($_SERVER['SCRIPT_FILENAME'])));
        }

        // DEPRECATED
        Alias::set('application', $this->getBasePath());
        Alias::set('webroot', dirname($_SERVER['SCRIPT_FILENAME']));

        if (isset($config['aliases'])) {
            $this->setAliases($config['aliases']);
            unset($config['aliases']);
        }

        if (isset($config['errorHandler'])) {
            $this->errorHandlerConfig = array_merge($this->errorHandlerConfig, $config['errorHandler']);
            unset($config['errorHandler']);
        }
        $this->initSystemHandlers();
        $this->preinit();
        $this->registerCoreComponents();

        $this->configure($config);

        $this->initTranslate();
        $this->attachBehaviors($this->behaviors);
        $this->preloadComponents();

        $this->initEvents();
        $this->initModules();

        $this->init();
    }

    public function getLocator()
    {
        if ($this->_locator === null) {
            $this->_locator = new ServiceLocator();
        }
        return $this->_locator;
    }

    protected function initModules()
    {
        foreach ($this->getModules() as $module => $config) {
            if (is_numeric($module)) {
                $name = $config;
                $className = '\\Modules\\' . ucfirst($name) . '\\' . ucfirst($name) . 'Module';
            } else {
                $className = $config['class'];
            }
            call_user_func([$className, 'preConfigure']);
        }
    }

    /**
     * Preinitializes the module.
     * This method is called at the beginning of the module constructor.
     * You may override this method to do some customized preinitialization work.
     * Note that at this moment, the module is not configured yet.
     * @see init
     */
    protected function preinit()
    {
    }

    /**
     * Loads static application components.
     */
    protected function preloadComponents()
    {
        foreach ($this->preload as $id) {
            $this->getComponent($id);
        }
    }

    /**
     * Checks whether the named component exists.
     * @param string $id application component ID
     * @return boolean whether the named application component exists (including both loaded and disabled.)
     */
    public function hasComponent($id)
    {
        if (!is_string($id)) {
            $id = array_shift($id);
        }
        return $this->getLocator()->has($id);
    }

    /**
     * Retrieves the named application component.
     * @param string $id application component ID (case-sensitive)
     * @param boolean $createIfNull whether to create the component if it doesn't exist yet.
     * @return IApplicationComponent the application component instance, null if the application component is disabled or does not exist.
     * @see hasComponent
     */
    public function getComponent($id, $createIfNull = true)
    {
        if ($this->hasComponent($id)) {
            return $this->getLocator()->get($id);
        } elseif (isset($this->_componentConfig[$id]) && $createIfNull) {
            $config = $this->_componentConfig[$id];
            if (!isset($config['enabled']) || $config['enabled']) {
                Mindy::app()->logger->debug("Loading \"$id\" application component", [], 'di');
                unset($config['enabled']);
                $component = Creator::createObject($config);
                $this->getLocator()->set($id, $component);
                return $component;
            }
        }
    }

    /**
     * Puts a component under the management of the module.
     * The component will be initialized by calling its {@link CApplicationComponent::init() init()}
     * method if it has not done so.
     * @param string $id component ID
     * @param array|IApplicationComponent $component application component
     * (either configuration array or instance). If this parameter is null,
     * component will be unloaded from the module.
     * @param boolean $merge whether to merge the new component configuration
     * with the existing one. Defaults to true, meaning the previously registered
     * component configuration with the same ID will be merged with the new configuration.
     * If set to false, the existing configuration will be replaced completely.
     * This parameter is available since 1.1.13.
     */
    public function setComponent($id, $component, $merge = true)
    {
        if ($component === null) {
            $this->getLocator()->clear($id);
            return;
        } elseif ($component instanceof IApplicationComponent) {
            $this->getLocator()->set($id, $component);
            return;
        } elseif ($this->getLocator()->has($id)) {
            if (isset($component['class']) && get_class($this->getLocator()->get($id)) !== $component['class']) {
                $this->getLocator()->clear($id);
                $this->_componentConfig[$id] = $component; //we should ignore merge here
                return;
            }

            Creator::configure($this->getLocator()->get($id), $component);
        } elseif (isset($this->_componentConfig[$id]['class'], $component['class']) && $this->_componentConfig[$id]['class'] !== $component['class']) {
            $this->_componentConfig[$id] = $component; //we should ignore merge here
            return;
        }

        if (isset($this->_componentConfig[$id]) && $merge) {
            $this->_componentConfig[$id] = Collection::mergeArray($this->_componentConfig[$id], $component);
        } else {
            $this->_componentConfig[$id] = $component;
        }
    }

    /**
     * Returns the application components.
     * @param boolean $loadedOnly whether to return the loaded components only. If this is set false,
     * then all components specified in the configuration will be returned, whether they are loaded or not.
     * Loaded components will be returned as objects, while unloaded components as configuration arrays.
     * This parameter has been available since version 1.1.3.
     * @return array the application components (indexed by their IDs)
     */
    public function getComponents($loadedOnly = true)
    {
        return $this->getLocator()->getComponents(!$loadedOnly);
    }

    /**
     * Defines the root aliases.
     * @param array $mappings list of aliases to be defined. The array keys are root aliases,
     * while the array values are paths or aliases corresponding to the root aliases.
     * For example,
     * <pre>
     * array(
     *    'models'=>'application.models',              // an existing alias
     *    'extensions'=>'application.extensions',      // an existing alias
     *    'backend'=>dirname(__FILE__).'/../backend',  // a directory
     * )
     * </pre>
     */
    public function setAliases($mappings)
    {
        foreach ($mappings as $name => $alias) {
            if (($path = Alias::get($alias)) !== false) {
                Alias::set($name, $path);
            } else {
                Alias::set($name, $alias);
            }
        }
    }

    public function initEvents()
    {
        $this->signal->handler($this, 'beginRequest', [$this, 'beginRequest']);
        $this->signal->handler($this, 'endRequest', [$this, 'endRequest']);
    }

    /**
     * Retrieves the named application module.
     * The module has to be declared in {@link modules}. A new instance will be created
     * when calling this method with the given ID for the first time.
     * @param string $id application module ID (case-sensitive)
     * @return Module the module instance, null if the module is disabled or does not exist.
     */
    public function getModule($id)
    {
        $id = ucfirst($id);
        if (isset($this->_modules[$id]) || array_key_exists($id, $this->_modules)) {
            return $this->_modules[$id];
        } elseif (isset($this->_moduleConfig[$id])) {
            $config = $this->_moduleConfig[$id];
            if (!isset($config['enabled']) || $config['enabled']) {
                Mindy::app()->logger->debug("Loading \"$id\" module", $config, 'module');
                $class = $config['class'];
                unset($config['class'], $config['enabled']);
                if ($this === Mindy::app()) {
                    $module = Creator::createObject($class, $id, null, $config);
                } else {
                    $module = Creator::createObject($class, $this->getId() . '/' . $id, $this, $config);
                }
                return $this->_modules[$id] = $module;
            }
        }
    }

    /**
     * Returns a value indicating whether the specified module is installed.
     * @param string $id the module ID
     * @return boolean whether the specified module is installed.
     * @since 1.1.2
     */
    public function hasModule($id)
    {
        return isset($this->_moduleConfig[$id]) || isset($this->_modules[$id]);
    }

    /**
     * Returns the configuration of the currently installed modules.
     * @return array the configuration of the currently installed modules (module ID => configuration)
     */
    public function getModules()
    {
        return $this->_moduleConfig;
    }

    /**
     * Configures the sub-modules of this module.
     *
     * Call this method to declare sub-modules and configure them with their initial property values.
     * The parameter should be an array of module configurations. Each array element represents a single module,
     * which can be either a string representing the module ID or an ID-configuration pair representing
     * a module with the specified ID and the initial property values.
     *
     * For example, the following array declares two modules:
     * <pre>
     * array(
     *     'admin',                // a single module ID
     *     'payment'=>array(       // ID-configuration pair
     *         'server'=>'paymentserver.com',
     *     ),
     * )
     * </pre>
     *
     * By default, the module class is determined using the expression <code>ucfirst($moduleID).'Module'</code>.
     * And the class file is located under <code>modules/$moduleID</code>.
     * You may override this default by explicitly specifying the 'class' option in the configuration.
     *
     * You may also enable or disable a module by specifying the 'enabled' option in the configuration.
     *
     * @param array $modules module configurations.
     */
    public function setModules($modules)
    {
        foreach ($modules as $id => $module) {
            if (is_int($id)) {
                $id = $module;
                $module = [];
            }
            if (!isset($module['class'])) {
                Alias::set($id, $this->getModulePath() . DIRECTORY_SEPARATOR . $id);
                $module['class'] = '\\Modules\\' . ucfirst($id) . '\\' . ucfirst($id) . 'Module';
            }

            if (isset($this->_moduleConfig[$id])) {
                $this->_moduleConfig[$id] = Collection::mergeArray($this->_moduleConfig[$id], $module);
            } else {
                $this->_moduleConfig[$id] = $module;
            }
        }
    }

    /**
     * Returns the directory that contains the application modules.
     * @return string the directory that contains the application modules. Defaults to the 'modules' subdirectory of {@link basePath}.
     */
    public function getModulePath()
    {
        if ($this->_modulePath !== null) {
            return $this->_modulePath;
        } else {
            return $this->_modulePath = $this->getBasePath() . DIRECTORY_SEPARATOR . 'Modules';
        }
    }

    /**
     * Sets the directory that contains the application modules.
     * @param string $value the directory that contains the application modules.
     * @throws Exception if the directory is invalid
     */
    public function setModulePath($value)
    {
        if (($this->_modulePath = realpath($value)) === false || !is_dir($this->_modulePath)) {
            throw new Exception(Mindy::t('base', 'The module path "{path}" is not a valid directory.', ['{path}' => $value]));
        }
    }

    public function __call($name, $args)
    {
        if (empty($args) && strpos($name, 'get') === 0) {
            $tmp = str_replace('get', '', $name);

            if ($this->getLocator()->has($tmp)) {
                return $this->getLocator()->get($tmp);
            } elseif ($this->getLocator()->has(lcfirst($tmp))) {
                return $this->getLocator()->get(lcfirst($tmp));
            }
        }

        return $this->__callInternal($name, $args);
    }

    public function __get($name)
    {
        if ($this->getLocator()->has($name)) {
            return $this->getLocator()->get($name);
        } else {
            return $this->__getInternal($name);
        }
    }

    /**
     * Runs the application.
     * This method loads static application components. Derived classes usually overrides this
     * method to do more application-specific tasks.
     * Remember to call the parent implementation so that static application components are loaded.
     */
    public function run()
    {
        $this->signal->send($this, 'beginRequest', $this);
        register_shutdown_function([$this, 'end'], 0, false);
        $this->processRequest();
        $this->signal->send($this, 'endRequest', $this);
    }

    /**
     * Terminates the application.
     * This method replaces PHP's exit() function by calling
     * {@link onEndRequest} before exiting.
     * @param integer $status exit status (value 0 means normal exit while other values mean abnormal exit).
     * @param boolean $exit whether to exit the current request. This parameter has been available since version 1.1.5.
     * It defaults to true, meaning the PHP's exit() function will be called at the end of this method.
     */
    public function end($status = 0, $exit = true)
    {
        $this->signal->send($this, 'endRequest', $this);
        if ($exit) {
            exit($status);
        }
    }

    /**
     * Raised right BEFORE the application processes the request.
     * @param BaseApplication $owner the event parameter
     */
    public function beginRequest($owner)
    {
        $owner->middleware->processRequest($owner->getComponent('request'));
    }

    /**
     * Raised right AFTER the application processes the request.
     * @param BaseApplication $owner the event parameter
     */
    public function endRequest($owner)
    {
        if (!$this->_ended) {
            $this->_ended = true;
        }
    }

    /**
     * Returns the unique identifier for the application.
     * @return string the unique identifier for the application.
     */
    public function getId()
    {
        if ($this->_id !== null) {
            return $this->_id;
        } else {
            return $this->_id = sprintf('%x', crc32($this->getBasePath() . $this->name));
        }
    }

    /**
     * Sets the unique identifier for the application.
     * @param string $id the unique identifier for the application.
     */
    public function setId($id)
    {
        $this->_id = $id;
    }

    /**
     * Returns the root path of the application.
     * @return string the root directory of the application. Defaults to 'protected'.
     */
    public function getBasePath()
    {
        return $this->_basePath;
    }

    /**
     * Sets the root directory of the application.
     * This method can only be invoked at the begin of the constructor.
     * @param string $path the root directory of the application.
     * @throws Exception if the directory does not exist.
     */
    public function setBasePath($path)
    {
        if (($this->_basePath = realpath($path)) === false || !is_dir($this->_basePath)) {
            throw new Exception(Mindy::t('base', 'Application base path "{path}" is not a valid directory.', ['{path}' => $path]));
        }
    }

    /**
     * Returns the directory that stores runtime files.
     * @return string the directory that stores runtime files. Defaults to 'protected/runtime'.
     */
    public function getRuntimePath()
    {
        if ($this->_runtimePath !== null) {
            return $this->_runtimePath;
        } else {
            $this->setRuntimePath($this->getBasePath() . DIRECTORY_SEPARATOR . 'runtime');
            return $this->_runtimePath;
        }
    }

    /**
     * Sets the directory that stores runtime files.
     * @param string $path the directory that stores runtime files.
     * @throws Exception if the directory does not exist or is not writable
     */
    public function setRuntimePath($path)
    {
        if (($runtimePath = realpath($path)) === false || !is_dir($runtimePath) || !is_writable($runtimePath)) {
            throw new Exception(Mindy::t('base', 'Application runtime path "{path}" is not valid. Please make sure it is a directory writable by the Web server process.', ['{path}' => $path]));
        }
        $this->_runtimePath = $runtimePath;
    }

    /**
     * Returns the time zone used by this application.
     * This is a simple wrapper of PHP function date_default_timezone_get().
     * @return string the time zone used by this application.
     * @see http://php.net/manual/en/function.date-default-timezone-get.php
     */
    public function getTimeZone()
    {
        return date_default_timezone_get();
    }

    /**
     * Sets the time zone used by this application.
     * This is a simple wrapper of PHP function date_default_timezone_set().
     * @param string $value the time zone used by this application.
     * @see http://php.net/manual/en/function.date-default-timezone-set.php
     */
    public function setTimeZone($value)
    {
        date_default_timezone_set($value);
    }

    /**
     * @return \Mindy\Controller\BaseController the currently active controller. Null is returned in this base class.
     * @since 1.1.8
     */
    public function getController()
    {
        return null;
    }

    /**
     * Returns the relative URL for the application.
     * This is a shortcut method to {@link CHttpRequest::getBaseUrl()}.
     * @param boolean $absolute whether to return an absolute URL. Defaults to false, meaning returning a relative one.
     * @return string the relative URL for the application
     * @see CHttpRequest::getBaseUrl()
     */
    public function getBaseUrl($absolute = false)
    {
        return $this->request->http->getBaseUrl($absolute);
    }

    /**
     * @return string the homepage URL
     */
    public function getHomeUrl()
    {
        if ($this->_homeUrl === null) {
            return $this->request->http->getBaseUrl() . '/';
        } else {
            return $this->_homeUrl;
        }
    }

    /**
     * @param string $value the homepage URL
     */
    public function setHomeUrl($value)
    {
        $this->_homeUrl = $value;
    }

    /**
     * @return object
     */
    protected function initTranslate()
    {
        return Translate::getInstance($this->locale);
    }

    /**
     * @return object
     */
    public function getTranslate()
    {
        return Translate::getInstance();
    }

    /**
     * Returns a global value.
     *
     * A global value is one that is persistent across users sessions and requests.
     * @param string $key the name of the value to be returned
     * @param mixed $defaultValue the default value. If the named global value is not found, this will be returned instead.
     * @return mixed the named global value
     * @see setGlobalState
     */
    public function getGlobalState($key, $defaultValue = null)
    {
        if ($this->_globalState === null) {
            $this->loadGlobalState();
        }

        return isset($this->_globalState[$key]) ? $this->_globalState[$key] : $defaultValue;
    }

    /**
     * Sets a global value.
     *
     * A global value is one that is persistent across users sessions and requests.
     * Make sure that the value is serializable and unserializable.
     * @param string $key the name of the value to be saved
     * @param mixed $value the global value to be saved. It must be serializable.
     * @param mixed $defaultValue the default value. If the named global value is the same as this value, it will be cleared from the current storage.
     * @see getGlobalState
     */
    public function setGlobalState($key, $value, $defaultValue = null)
    {
        if ($this->_globalState === null) {
            $this->loadGlobalState();
        }

        $changed = $this->_stateChanged;
        if ($value === $defaultValue && isset($this->_globalState[$key])) {
            unset($this->_globalState[$key]);
            $this->_stateChanged = true;
        } elseif (!isset($this->_globalState[$key]) || $this->_globalState[$key] !== $value) {
            $this->_globalState[$key] = $value;
            $this->_stateChanged = true;
        }

        if ($this->_stateChanged !== $changed) {
            $this->signal->handler($this, 'endRequest', [$this, 'saveGlobalState']);
        }
    }

    /**
     * Returns user-defined parameters.
     * @return \Mindy\Helper\Collection the list of user-defined parameters
     */
    public function getParams()
    {
        if ($this->_params !== null) {
            return $this->_params;
        } else {
            $this->_params = new Collection([]);
            return $this->_params;
        }
    }

    /**
     * Sets user-defined parameters.
     * @param array $value user-defined parameters. This should be in name-value pairs.
     */
    public function setParams($value)
    {
        $params = $this->getParams();
        foreach ($value as $k => $v) {
            $params->add($k, $v);
        }
    }

    /**
     * Clears a global value.
     *
     * The value cleared will no longer be available in this request and the following requests.
     * @param string $key the name of the value to be cleared
     */
    public function clearGlobalState($key)
    {
        $this->setGlobalState($key, true, true);
    }

    /**
     * Loads the global state data from persistent storage.
     * @see getStatePersister
     * @throws Exception if the state persister is not available
     */
    public function loadGlobalState()
    {
        if (($this->_globalState = $this->statePersister->load()) === null) {
            $this->_globalState = [];
        }
        $this->_stateChanged = false;
    }

    /**
     * Saves the global state data into persistent storage.
     * @see getStatePersister
     * @throws Exception if the state persister is not available
     */
    public function saveGlobalState()
    {
        if ($this->_stateChanged) {
            $this->_stateChanged = false;
            $this->statePersister->save($this->_globalState);
        }
    }

    /**
     * Initializes the error handlers.
     */
    protected function initSystemHandlers()
    {
        if (MINDY_ENABLE_EXCEPTION_HANDLER || MINDY_ENABLE_ERROR_HANDLER) {
            $handler = Creator::createObject($this->errorHandlerConfig);
            if (MINDY_ENABLE_EXCEPTION_HANDLER) {
                set_exception_handler([$handler, 'handleException']);
            }
            if (MINDY_ENABLE_ERROR_HANDLER) {
                set_error_handler([$handler, 'handleError'], error_reporting());
            }
        }
    }

    /**
     * Registers the core application components.
     * @see setComponents
     */
    protected function registerCoreComponents()
    {
        $components = [
            'securityManager' => [
                'class' => '\Mindy\Security\SecurityManager',
            ],
            'statePersister' => [
                'class' => '\Mindy\Base\StatePersister',
            ],
            'urlManager' => [
                'class' => '\Mindy\Router\UrlManager',
            ],
            'request' => [
                'class' => '\Mindy\Http\Request',
            ],
            'format' => [
                'class' => '\Mindy\Locale\Formatter',
            ],
            'signal' => [
                'class' => '\Mindy\Event\EventManager',
            ],
            'session' => [
                'class' => '\Mindy\Session\HttpSession',
            ],
            'mail' => [
                'class' => '\Mindy\Mail\Mailer',
            ],
            'logger' => [
                'class' => '\Mindy\Logger\LoggerManager',
                'handlers' => [
                    'null' => [
                        'class' => '\Mindy\Logger\Handler\NullHandler',
                        'level' => 'ERROR'
                    ],
                    'console' => [
                        'class' => '\Mindy\Logger\Handler\StreamHandler',
                    ],
                    'users' => [
                        'class' => '\Mindy\Logger\Handler\RotatingFileHandler',
                        'alias' => 'application.runtime.users',
                        'level' => 'INFO',
                        'formatter' => 'users'
                    ],
                    'mail_admins' => [
                        'class' => '\Mindy\Logger\Handler\SwiftMailerHandler',
                    ],
                ],
                'formatters' => [
                    'default' => [
                        'class' => '\Bramus\Monolog\Formatter\ColoredLineFormatter',
                    ],
                    'users' => [
                        'class' => '\Mindy\Logger\Formatters\LineFormatter',
                        'format' => "%datetime% %message%\n"
                    ]
                ],
                'loggers' => [
                    'users' => [
                        'class' => '\Monolog\Logger',
                        'handlers' => ['users'],
                    ],
                ]
            ],
        ];

        $this->setComponents($components);
    }

    public function setComponents($components, $merge = true)
    {
        foreach ($components as $name => $component) {
            $this->getLocator()->set($name, $component);
        }
    }
}
