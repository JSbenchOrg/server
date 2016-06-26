<?php
namespace JSB;

class Exception extends \Exception
{
    const EXISTING_SLUG = 'EXISTING_SLUG';
    const INVALID_UPDATE = 'INVALID_UPDATE';
    const NOT_FOUND = 'NOT_FOUND';
    const INVALID_REQUEST_BODY = 'INVALID_REQUEST_BODY';
    const INCOMPLETE_ERROR_STRUCTURE = 'INCOMPLETE_ERROR_STRUCTURE';
    const APPLICATION_ERROR = 'APPLICATION_ERROR';

    protected $data;

    public function __construct($message, $code)
    {
        $this->message = $message;
        $this->code = $code;
    }

    public function getDetails()
    {
        return $this->data;
    }

    public function withDetails($data)
    {
        $this->data = $data;
        return $this;
    }
}
