<?php

class HTTPTools
{

    public function is_ajax()
    {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        return $isAjax;
    }

    public function is_post()
    {
    	//TODO: implement
        
    }
}