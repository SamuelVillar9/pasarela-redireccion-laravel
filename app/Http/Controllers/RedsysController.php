<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RedsysController extends Controller
{
    public function index()
    {

        $claveComercio = "sq7HjrUOBfKmC576ILgskD5srU870gJ7"; //Clave de comercio de test

        $redsys = new RedsysAPI;

        $redsys->setParameter("DS_MERCHANT_AMOUNT", "1000");
        $redsys->setParameter("DS_MERCHANT_ORDER", time());
        $redsys->setParameter("DS_MERCHANT_MERCHANTCODE", "999008881");
        $redsys->setParameter("DS_MERCHANT_CURRENCY", "978");
        $redsys->setParameter("DS_MERCHANT_TRANSACTIONTYPE", "0");
        $redsys->setParameter("DS_MERCHANT_TERMINAL", "1");
        $redsys->setParameter("DS_MERCHANT_MERCHANTURL", "http://www.prueba.com/urlNotificacion.php");
        $redsys->setParameter("DS_MERCHANT_URLKO", "http://www.prueba.com/urlKO.php");
        $redsys->setParameter("DS_MERCHANT_URLOK", "http://www.prueba.com/urlOK.php");

        $merchantParameters = $redsys->createMerchantParameters();
        $signature = $redsys->createMerchantSignature($claveComercio);

        return view('redsys.form', compact('redsys', 'merchantParameters', 'signature'));
    }
}

class RedsysAPI
{

    /******  Array de DatosEntrada ******/
    var $vars_pay = array();

    /******  Set parameter ******/
    function setParameter($key, $value)
    {
        $this->vars_pay[$key] = $value;
    }

    /******  Get parameter ******/
    function getParameter($key)
    {
        return $this->vars_pay[$key];
    }


    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////
    ////////////					FUNCIONES AUXILIARES:							  ////////////
    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////


    /******  3DES Function  ******/
    function encrypt_3DES($message, $key)
    {
        // Se cifra
        $l = ceil(strlen($message) / 8) * 8;
        return substr(openssl_encrypt($message . str_repeat("\0", $l - strlen($message)), 'des-ede3-cbc', $key, OPENSSL_RAW_DATA, "\0\0\0\0\0\0\0\0"), 0, $l);
    }

    /******  Base64 Functions  ******/
    function base64_url_encode($input)
    {
        return strtr(base64_encode($input), '+/', '-_');
    }
    function encodeBase64($data)
    {
        $data = base64_encode($data);
        return $data;
    }
    function base64_url_decode($input)
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }
    function decodeBase64($data)
    {
        $data = base64_decode($data);
        return $data;
    }

    /******  MAC Function ******/
    function mac256($ent, $key)
    {
        $res = hash_hmac('sha256', $ent, $key, true); //(PHP 5 >= 5.1.2)
        return $res;
    }


    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////
    ////////////	   FUNCIONES PARA LA GENERACIÓN DEL FORMULARIO DE PAGO:			  ////////////
    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////

    /******  Obtener Número de pedido ******/
    function getOrder()
    {
        $numPedido = "";
        if (empty($this->vars_pay['DS_MERCHANT_ORDER'])) {
            $numPedido = $this->vars_pay['Ds_Merchant_Order'];
        } else {
            $numPedido = $this->vars_pay['DS_MERCHANT_ORDER'];
        }
        return $numPedido;
    }
    /******  Convertir Array en Objeto JSON ******/
    function arrayToJson()
    {
        $json = json_encode($this->vars_pay); //(PHP 5 >= 5.2.0)
        return $json;
    }
    function createMerchantParameters()
    {
        // Se transforma el array de datos en un objeto Json
        $json = $this->arrayToJson();
        // Se codifican los datos Base64
        return $this->encodeBase64($json);
    }
    function createMerchantSignature($key)
    {
        // Se decodifica la clave Base64
        $key = $this->decodeBase64($key);
        // Se genera el parámetro Ds_MerchantParameters
        $ent = $this->createMerchantParameters();
        // Se diversifica la clave con el Número de Pedido
        $key = $this->encrypt_3DES($this->getOrder(), $key);
        // MAC256 del parámetro Ds_MerchantParameters
        $res = $this->mac256($ent, $key);
        // Se codifican los datos Base64
        return $this->encodeBase64($res);
    }



    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////// FUNCIONES PARA LA RECEPCIÓN DE DATOS DE PAGO (Notif, URLOK y URLKO): ////////////
    //////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////

    /******  Obtener Número de pedido ******/
    function getOrderNotif()
    {
        $numPedido = "";
        if (empty($this->vars_pay['Ds_Order'])) {
            $numPedido = $this->vars_pay['DS_ORDER'];
        } else {
            $numPedido = $this->vars_pay['Ds_Order'];
        }
        return $numPedido;
    }
    function getOrderNotifSOAP($datos)
    {
        $posPedidoIni = strrpos($datos, "<Ds_Order>");
        $tamPedidoIni = strlen("<Ds_Order>");
        $posPedidoFin = strrpos($datos, "</Ds_Order>");
        return substr($datos, $posPedidoIni + $tamPedidoIni, $posPedidoFin - ($posPedidoIni + $tamPedidoIni));
    }
    function getRequestNotifSOAP($datos)
    {
        $posReqIni = strrpos($datos, "<Request");
        $posReqFin = strrpos($datos, "</Request>");
        $tamReqFin = strlen("</Request>");
        return substr($datos, $posReqIni, ($posReqFin + $tamReqFin) - $posReqIni);
    }
    function getResponseNotifSOAP($datos)
    {
        $posReqIni = strrpos($datos, "<Response");
        $posReqFin = strrpos($datos, "</Response>");
        $tamReqFin = strlen("</Response>");
        return substr($datos, $posReqIni, ($posReqFin + $tamReqFin) - $posReqIni);
    }
    /******  Convertir String en Array ******/
    function stringToArray($datosDecod)
    {
        $this->vars_pay = json_decode($datosDecod, true); //(PHP 5 >= 5.2.0)
    }
    function decodeMerchantParameters($datos)
    {
        // Se decodifican los datos Base64
        $decodec = $this->base64_url_decode($datos);
        // Los datos decodificados se pasan al array de datos
        $this->stringToArray($decodec);
        return $decodec;
    }
    function createMerchantSignatureNotif($key, $datos)
    {
        // Se decodifica la clave Base64
        $key = $this->decodeBase64($key);
        // Se decodifican los datos Base64
        $decodec = $this->base64_url_decode($datos);
        // Los datos decodificados se pasan al array de datos
        $this->stringToArray($decodec);
        // Se diversifica la clave con el Número de Pedido
        $key = $this->encrypt_3DES($this->getOrderNotif(), $key);
        // MAC256 del parámetro Ds_Parameters que envía Redsys
        $res = $this->mac256($datos, $key);
        // Se codifican los datos Base64
        return $this->base64_url_encode($res);
    }
    /******  Notificaciones SOAP ENTRADA ******/
    function createMerchantSignatureNotifSOAPRequest($key, $datos)
    {
        // Se decodifica la clave Base64
        $key = $this->decodeBase64($key);
        // Se obtienen los datos del Request
        $datos = $this->getRequestNotifSOAP($datos);
        // Se diversifica la clave con el Número de Pedido
        $key = $this->encrypt_3DES($this->getOrderNotifSOAP($datos), $key);
        // MAC256 del parámetro Ds_Parameters que envía Redsys
        $res = $this->mac256($datos, $key);
        // Se codifican los datos Base64
        return $this->encodeBase64($res);
    }
    /******  Notificaciones SOAP SALIDA ******/
    function createMerchantSignatureNotifSOAPResponse($key, $datos, $numPedido)
    {
        // Se decodifica la clave Base64
        $key = $this->decodeBase64($key);
        // Se obtienen los datos del Request
        $datos = $this->getResponseNotifSOAP($datos);
        // Se diversifica la clave con el Número de Pedido
        $key = $this->encrypt_3DES($numPedido, $key);
        // MAC256 del parámetro Ds_Parameters que envía Redsys
        $res = $this->mac256($datos, $key);
        // Se codifican los datos Base64
        return $this->encodeBase64($res);
    }
}
