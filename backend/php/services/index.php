<?php
  /*
   * qooxdoo - the new era of web interface development
   *
   * Copyright:
   *   (C) 2006 by Derrell Lipman
   *       All rights reserved
   *
   * License:
   *   LGPL 2.1: http://creativecommons.org/licenses/LGPL/2.1/
   *
   * Internet:
   *   * http://qooxdoo.org
   *
   * Author:
   *   * Derrell Lipman
   *     derrell dot lipman at unwireduniverse dot com
   */

  /*
   * This is a simple JSON-RPC server.  We receive a service name in
   * dot-separated path format and expect to find the class containing the
   * service in a file of the service name (with dots converted to slashes and
   * ".php" appended).
   */

require "JSON.phps";

/**
 * The location of the service class directories.
 */
define("servicePathPrefix",                "");



/*
 * JSON-RPC error origins
 */
define("JsonRpcError_Origin_Server",      1);
define("JsonRpcError_Origin_Application", 2);
define("JsonRpcError_Origin_Transport",   3); // never generated by server
define("JsonRpcError_Origin_Client",      4); // never generated by server



/*
 * JSON-RPC server-generated error codes
 */

/**
 * Error code, value 0: Unknown Error
 *
 * The default error code, used only when no specific error code is passed to
 * the JsonRpcError constructor.  This code should generally not be used.
 */
define("JsonRpcError_Unknown",      0);

/**
 * Error code, value 1: Illegal Service
 *
 * The service name contains illegal characters or is otherwise deemed
 * unacceptable to the JSON-RPC server.
 */
define("JsonRpcError_IllegalService",      1);

/**
 * Error code, value 2: Service Not Found
 *
 * The requested service does not exist at the JSON-RPC server.
 */
define("JsonRpcError_ServiceNotFound",     2);

/**
 * Error code, value 3: Class Not Found
 *
 * If the JSON-RPC server divides service methods into subsets (classes), this
 * indicates that the specified class was not found.  This is slightly more
 * detailed than "Method Not Found", but that error would always also be legal
 * (and true) whenever this one is returned.
 */
define("JsonRpcError_ClassNotFound",       3);

/**
 * Error code, value 4: Method Not Found
 *
 * The method specified in the request is not found in the requested service.
 */
define("JsonRpcError_MethodNotFound",      4);

/**
 * Error code, value 5: Parameter Mismatch
 *
 * If a method discovers that the parameters (arguments) provided to it do not
 * match the requisite types for the method's parameters, it should return
 * this error code to indicate so to the caller.
 */
define("JsonRpcError_ParameterMismatch",   5);

/**
 * Error code, value 6: Permission Denied
 *
 * A JSON-RPC service provider can require authentication, and that
 * authentication can be implemented such the method takes authentication
 * parameters, or such that a method or class of methods requires prior
 * authentication.  If the caller has not properly authenticated to use the
 * requested method, this error code is returned.
 */
define("JsonRpcError_PermissionDenied",    6);


define("ScriptTransport_NotInUse",         -1);

function SendReply($reply, $scriptTransportId)
{
    /* If not using ScriptTransport... */
    if ($scriptTransportId == ScriptTransport_NotInUse)
    {
        /* ... then just output the reply. */
        print $reply;
    }
    else
    {
        /* Otherwise, we need to add a call to a qooxdoo-specific function */
        $reply =
            "qx.io.remote.ScriptTransport._requestFinished(" .
            $scriptTransportId . ", " . $reply .
            ");";
        print $reply;
    }
}


/*
 * class JsonRpcError
 *
 * This class allows service methods to easily provide error information for
 * return via JSON-RPC.
 */
class JsonRpcError
{
    var             $json;
    var             $data;
    var             $id;
    var             $scriptTransportId;
    
    function JsonRpcError($json,
                          $origin = JsonRpcError_Origin_Server,
                          $code = JsonRpcError_Unknown,
                          $message = "Unknown error")
    {
        $this->json = $json;
        $this->data = array("origin"  => $origin,
                            "code"    => $code,
                            "message" => $message);

        /* Assume we're not using ScriptTransporrt */
        $this->scriptTransportId = ScriptTransport_NotInUse;
    }
    
    function SetOrigin($origin)
    {
        $this->data["origin"] = $origin;
    }

    function SetError($code, $message)
    {
        $this->data["code"] = $code;
        $this->data["message"] = $message;
    }
    
    function SetId($id)
    {
        $this->id = $id;
    }
    
    function SetScriptTransportId($id)
    {
        $this->scriptTransportId = $id;
    }
    
    function SendAndExit()
    {
        $error = $this;
        $id = $this->id;
        $ret = array("error" => $this->data,
                     "id"    => $id);
        SendReply($this->json->encode($ret), $this->scriptTransportId);
        exit;
    }
}

function debug($str)
{
    static $fw = null;
    if ($fw === null)
    {
        $fw = fopen("/tmp/phpinfo", "w");
    }
    fputs($fw, $str, strlen($str));
    fflush($fw);
}

/*
 * Create a new instance of JSON and get the JSON-RPC request from
 * the POST data.
 */
$json = new JSON();
$error = new JsonRpcError($json);

/* Assume (default) we're not using ScriptTransport */
$scriptTransportId = ScriptTransport_NotInUse;

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
    /*
     * We might have received either of two submission methods here.  If this
     * was form data (as would be received via an IframeTransport request), we
     * expect "_data_=<url-encoded-json-rpc>"; otherwise (XmlHttpTransport)
     * we'll have simply <json-rpc>, not url-encoded and with no "_data_=".
     * The "Content-Type" field should be one of our two supported variants:
     * text/json or application/x-json-form-urlencoded.  If neither, or if
     * there is more than one form field provided or if the first form field
     * name is not '_data_', it's an error.
     */
    switch($_SERVER["CONTENT_TYPE"])
    {
    case "text/json":
        /* We found literal POSTed json-rpc data (we hope) */
        $input = file_get_contents('php://input');
        $jsonInput = $json->decode($input);
        break;
    
    case "application/x-www-form-urlencoded":
        /*
         * We received a form submission.  See what fields were provided.
         * There must be only "_data_"
         */
        if (count($_POST) == 1 && isset($_POST["_data_"]))
        {
            /* $_POST["_data_"] has quotes escaped.  php://input doesn't. */
            $input = file_get_contents('php://input');
            $inputFields = explode("=", $input);
            $jsonInput = $json->decode(urldecode($inputFields[1]));
            break;
        }
    
        /* fall through to default */
    
    default:
        /*
         * This request was not issued with JSON-RPC so echo the error rather
         * than issuing a JsonRpcError response.
         */
        echo
            "JSON-RPC request expected; " .
            "unexpected data received";
        exit;
    }
}
else if ($_SERVER["REQUEST_METHOD"] == "GET" &&
         isset($_GET["_ScriptTransport_id"]) &&
         isset($_GET["_ScriptTransport_data"]))
{
    /* We have what looks like a valid ScriptTransport request */
    $scriptTransportId = $_GET["_ScriptTransport_id"];
    $error->SetScriptTransportId($scriptTransportId);
    $input = $_GET["_ScriptTransport_data"];
    $jsonInput = $json->decode(stripslashes($input));
}
else
{
    /*
     * This request was not issued with JSON-RPC so echo the error rather than
     * issuing a JsonRpcError response.
     */
    echo "Services require JSON-RPC<br>";
    exit;
}

/* Ensure that this was a JSON-RPC service request */
if (! isset($jsonInput) ||
    ! isset($jsonInput->service) ||
    ! isset($jsonInput->method) ||
    ! isset($jsonInput->params))
{
    /*
     * This request was not issued with JSON-RPC so echo the error rather than
     * issuing a JsonRpcError response.
     */
    echo
        "JSON-RPC request expected; " .
        "service, method or params missing<br>";
    exit;
}

/*
 * Ok, it looks like JSON-RPC, so we'll return an Error object if we encounter
 * errors from here on out.
 */
$error->SetId($jsonInput->id);

/*
 * Ensure the requested service name is kosher.  A service name should be:
 *
 *   - a dot-separated sequences of strings; no adjacent dots
 *   - first character of each string is in [a-zA-Z] 
 *   - other characters are in [_a-zA-Z0-9]
 */

/* First check for legal characters */
if (ereg("^[_.a-zA-Z0-9]+$", $jsonInput->service) === false)
{
    /* There's some illegal character in the service name */
    $error->SetError(JsonRpcError_IllegalService,
                     "Illegal character found in service name.");
    $error->SendAndExit();
    /* never gets here */
}

/* Now ensure there are no double dots */
if (strstr($jsonInput->service, "..") !== false)
{
    $error->SetError(JsonRpcError_IllegalService,
                     "Illegal use of two consecutive dots in service name");
    $error->SendAndExit();
}

/* Explode the service name into its dot-separated parts */
$serviceComponents = explode(".", $jsonInput->service);

/* Ensure that each component begins with a letter */
for ($i = 0; $i < count($serviceComponents); $i++)
{
    if (ereg("^[a-zA-Z]", $serviceComponents[$i]) === false)
    {
        $error->SetError(JsonRpcError_IllegalService,
                         "A service name component does not begin with a letter");
        $error->SendAndExit();
        /* never gets here */
    }
}

/*
 * Now replace all dots with slashes so we can locate the service script.  We
 * also retain the exploded components of the path, as the class name of the
 * service is the last component of the path.
 */
$servicePath = implode("/", $serviceComponents);

/* Try to load the requested service */
if ((@include servicePathPrefix . $servicePath . ".php") === false)
{
    /* Couldn't find the requested service */
    $error->SetError(JsonRpcError_ServiceNotFound,
                     "Service `$servicePath` not found.");
    $error->SendAndExit();
    /* never gets here */
}

/* The service class is the last component of the service name */
$className = "class_" . $serviceComponents[count($serviceComponents) - 1];

/* Ensure that the class exists */
if (! class_exists($className))
{
    $error->SetError(JsonRpcError_ClassNotFound,
                     "Service class `$className` not found.");
    $error->SendAndExit();
    /* never gets here */
}

/* Instantiate the service */
$service = new $className();

/* Now that we've instantiated service, we should find the requested method */
$method = "method_" . $jsonInput->method;
if (! method_exists($service, $method))
{
    $error->SetError(JsonRpcError_MethodNotFound,
                     "Method `$method` not found " .
                     "in service class `$className`.");
    $error->SendAndExit();
    /* never gets here */
}

/* Errors from here on out will be Application-generated */
$error->SetOrigin(JsonRpcError_Origin_Application);

/* Call the requested method passing it the provided params */
$output = $service->$method($jsonInput->params, &$error);

/* See if the result of the function was actually an error */
if (get_class($output) == "JsonRpcError")
{
    /* Yup, it was.  Return the error */
    $error->SendAndExit();
    /* never gets here */
}

/* Give 'em what they came for! */
$ret = array("result" => $output,
             "id"     => $jsonInput->id);
SendReply($json->encode($ret), $scriptTransportId);

?>
