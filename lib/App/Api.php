<?php
namespace Limbonia\App;

/**
 * Limbonia API App Class
 *
 * This allows the basic app retrieve data base on the API URL and return
 * that data in JSON format
 *
 * @author Lonnie Blansett <lonnie@limbonia.tech>
 * @package Limbonia
 */
class Api extends \Limbonia\App\Web
{
  protected static $hJsonData = null;

  public static function jsonData()
  {
    if (is_null(self::$hJsonData))
    {
      self::$hJsonData = json_decode(file_get_contents("php://input"), true);
    }

    return self::$hJsonData;
  }

  /**
   * The app constructor
   *
   * NOTE: This constructor should only be used by the factory and *never* directly
   *
   * @param array $hConfig - A hash of configuration data
   */
  protected function __construct(array $hConfig = [])
  {
    \Limbonia\App::__construct($hConfig);
    $oServer = \Limbonia\Input::singleton('server');

    if (empty($this->oDomain))
    {
      if (!empty($oServer['context_prefix']) && !empty($oServer['context_document_root']))
      {
         $this->oDomain = new \Limbonia\Domain($oServer['server_name'] . $oServer['context_prefix'], $oServer['context_document_root']);
      }
      else
      {
        $this->oDomain = \Limbonia\Domain::getByDirectory($oServer['document_root']);
      }

      $this->hConfig['baseuri'] = $this->oDomain->uri;
    }

    if (empty($this->oDomain->uri))
    {
      $this->oRouter = \Limbonia\Router::singleton();
    }
    //if the request is coming from a URI
    else
    {
      //then override the default Router object
      $this->oRouter = \Limbonia\Router::fromArray
      ([
        'uri' => $oServer['request_uri'],
        'baseurl' => $this->oDomain->uri,
        'method' => strtolower($oServer['request_method'])
      ]);
    }
  }

  /**
   * Get the user associated with the specified auth_token and return it
   *
   * @param type $sAuthToken
   * @return type
   */
  public function userByAuthToken($sAuthToken)
  {
    $oUser = \Limbonia\Model\User::getByAuthToken($sAuthToken, $this->getDB());
    $oUser->setApp($this);
    return $oUser;
  }

  /**
   * Get the user associated with the specified API key and return it
   *
   * @param string $sApiKey
   * @return \Limbonia\Model\User
   */
  public function userByApiKey($sApiKey)
  {
    $oUser = \Limbonia\Model\User::getByApiKey($sApiKey, $this->getDB());
    $oUser->setApp($this);
    return $oUser;
  }

    /**
   * Generate and return the current user
   *
   * @return \Limbonia\Model\User
   * @throws \Exception
   */
  protected function generateUser()
  {
    $oServer = \Limbonia\Input::singleton('server');

    if (!empty($oServer['http_auth_token']))
    {
      return $this->userByAuthToken($oServer['http_auth_token']);
    }

    if (!empty($oServer['http_api_key']))
    {
      return $this->userByApiKey($oServer['http_api_key']);
    }

    return $this->modelFactory('user');
  }

  /**
   * Render this app instance for output and return that data
   *
   * @return string
   */
  protected function render()
  {
    if (is_null($this->oRouter->controller))
    {
      throw new \Exception('No controller found');
    }

    $oController = $this->controllerFactory($this->oRouter->controller);
    $xResult = $oController->processApi();

    if ($xResult instanceof \Limbonia\ModelList || $xResult instanceof \Limbonia\Model || $xResult instanceof \Limbonia\Interfaces\Result)
    {
      return $xResult->getAll();
    }

    return $xResult;
  }

  /**
   * Run everything needed to react to input and display data in the way this app is intended
   */
  public function run()
  {
    try
    {
      ob_start();
      $this->oUser = $this->generateUser();
      $xOutput = $this->render();
    }
    catch (\Limbonia\Exception\Web $e)
    {
      http_response_code($e->getResponseCode());
      $xOutput =
      [
        'code' => $e->getCode(),
        'message' => $e->getMessage()
      ];
    }
    catch (\Exception $e)
    {
      http_response_code(400);
      $xOutput =
      [
        'code' => $e->getCode(),
        'message' => $e->getMessage()
      ];
    }
    finally
    {
      ob_end_clean();
      die(parent::outputJson($xOutput));
    }
  }
}