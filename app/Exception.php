<?php
namespace JSB;

class Exception extends \Exception
{
    protected $data;

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
