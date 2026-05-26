<?php

class PaynetRequest
{
    public $ExternalDate;
    public $ExternalID;
    public $Currency = 498;
    public $Merchant;
    public $LinkSuccess;
    public $LinkCancel;
    public $ExpiryDate;
    // ru, ro, en
    public $Lang;
    public $Service = array();
    public $Products = array();
    public $Customer = array();
    public $Amount;
}
