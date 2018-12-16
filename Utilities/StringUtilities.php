<?php

namespace NTI\EmailBundle\Utilities;

class StringUtilities {
    public static function BeautifyException(\Exception $ex) {
        $str = "";

        $date = new \DateTime();

        $str .= "Date: ".$date->format('m/d/Y h:i:s A') . "\r\n";
        $str .= "File: ".$ex->getFile().":".$ex->getLine()."\r\n";
        $str .= "Message: ".$ex->getMessage()."\r\n";
        $str .= "Stack Trace: \r\n";
        $str .= $ex->getTraceAsString();

        return $str;
    }
}