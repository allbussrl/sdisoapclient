<?php

namespace Sengerio;

class SdiSoapClient extends \SoapClient
{
    const REGEX_ENV  = '/<soap[\s\S]*nvelope>/i';
    const REGEX_XOP  = '/<xop:include[\s\S]*cid:%s@[\s\S]*?<\/xop:Include>/i';
    const REGEX_CID  = '/cid:([0-9a-zA-Z-]+)@/i';
    const REGEX_CON  = '/Content-ID:[\s\S].+?%s[\s\S].+?>([\s\S]*?)--MIMEBoundary/i';
    

    /**
     * @inheritdoc
     */
    public function __doRequest($request, $location, $action, $version, $one_way = null)
    {
        $response = parent::__doRequest($request, $location, $action, $version, $one_way);
        
        
        $xml_response = null;

        // recupera la risposta xml isolandola da quella mtom
        preg_match(self::REGEX_ENV, $response, $xml_response);

        if ( !is_array($xml_response) || count($xml_response) <= 0 ) {
            throw new \Exception('No XML has been found.');
        }
        // prendiamo il primo elemento dell'array
        $xml_response = reset($xml_response);

        // recuperiamo i tag xop
        $xop_elements = null;
        preg_match_all(sprintf(self::REGEX_XOP, '.*'), $response, $xop_elements);
        // prendiamo il primo elemento dell'array
        $xop_elements = reset($xop_elements);

        if ( is_array($xop_elements) && count($xop_elements) > 0 ) {
            foreach ($xop_elements as $xop_element) {

                // recuperiamo il cid
                $matches = null;
                preg_match(self::REGEX_CID, $xop_element, $matches);

                if( isset($matches[1]) ){
                    $cid = $matches[1];

                    // recuperiamo il contenuto associato al cid
                    $matches = null;
                    preg_match(sprintf(self::REGEX_CON, $cid), $response, $matches);

                    if( isset($matches[1]) ){
                        $binary = trim($matches[1]);
                        $binary = base64_encode($binary);

                        // sostituiamo il tag xop:Include con base64_encode(binary)
                        // nota: SoapClient fa automaticamente il base64_decode(binary)
                        $old_xml_response = $xml_response;
                        $xml_response = preg_replace(sprintf(self::REGEX_XOP, $cid), $binary, $xml_response);
                        if( $old_xml_response === $xml_response ){
                            throw new \Exception('xop replace failed');
                        }
                    } else {
                        throw new \Exception('binary not found.');
                    }
                } else {
                    throw new \Exception('cid not found.');
                }
            }
        }

        return $xml_response;
    }
    
}

