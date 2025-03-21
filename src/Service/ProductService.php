<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;
use Psr\Log\LoggerInterface;

class ProductService
{
    private array $config;
    private array $products;
    private array $plans;
    private LoggerInterface $logger;

    public function __construct(string $rootPath)
    {
        // Load YAML-files
        $this->config = Yaml::parseFile($rootPath . '/config/healthtrain/config.yml');
        $this->products = Yaml::parseFile($rootPath . '/config/healthtrain/products.yml');
        $this->plans = Yaml::parseFile($rootPath . '/config/healthtrain/plans.yml');
    }

    public function getPlan(string $planKey, bool $testmode = true): array
    {   
        if (!array_key_exists($planKey, $this->plans)) {
            throw new \InvalidArgumentException("Plan does not exist: " . $planKey);
        }
        $plan = $testmode ? $this->plans[$planKey]['testmode'] : $this->plans[$planKey]['livemode'];
        $plan['config'] = $this->getConfig($plan['config']);
        $plan['products'] = $this->getProducts($plan['products']);

        return $plan;
    }

    public function getProduct(string $productId): array
    {
        if (!array_key_exists($productId, $this->products)) {
            throw new \InvalidArgumentException("Product does not exist: " . $productId);
        }
        return $this->products[$productId];
    }

    public function getProducts(array $productIds): array
    {
        $productArray = [];
        foreach ($productIds as $productId) {
            try {
                $productArray[$productId] = $this->getProduct($productId);
            } catch (\InvalidArgumentException $e) {
                $this->logger->error("Product not found: " . $productId);
            }
        }
        return $productArray;
    }

    public function getConfig(string $configKey): array
    {
        if (!array_key_exists($configKey, $this->config)) {
            throw new \InvalidArgumentException("Config does not exist: " . $configKey);
        }
        return $this->config[$configKey];
    }
}