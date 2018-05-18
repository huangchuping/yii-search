<?php

namespace hcp\search\components;

use yii\base\Object;
use Elastica\Client;
use Psr\Log\LoggerInterface;

class Elastica extends Object
{
    public $host;

    public $port;

    private $elasticClient;

    private $logger;

    private $log = false;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        $config = (array) $this;

        $this->elasticClient = new Client($config);
    }

    public function getClient()
    {
        return $this->elasticClient;
    }
}
