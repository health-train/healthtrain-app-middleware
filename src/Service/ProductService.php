<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;
use Psr\Log\LoggerInterface;

class ProductService
{
    private array $config;
    private array $plans;

    public function __construct(string $rootPath)
    {
        // Load YAML-files
        $this->config = Yaml::parseFile($rootPath . '/config/healthtrain/config.yml');
        $this->plans = Yaml::parseFile($rootPath . '/config/healthtrain/plans.yml');
    }

    public function getPlan(string $planKey, bool $testmode = true): array
    {   
        if (!array_key_exists($planKey, $this->plans)) {
            throw new \InvalidArgumentException("Plan does not exist: " . $planKey);
        }
        $plan = $testmode ? $this->plans[$planKey]['testmode'] : $this->plans[$planKey]['livemode'];
        $plan['config'] = $this->getConfig($plan['config']);

        return $plan;
    }

    public function getProduct(string $productId): ?array
    {
        foreach ($this->plans as $planName => $planData) {
            foreach (['livemode', 'testmode'] as $mode) {
                if (!isset($planData[$mode]['products'])) {
                    continue;
                }
                
                foreach ($planData[$mode]['products'] as $key => $value) {
                    if ($key === $productId) {
                        return $value;
                    }
                }
            }
        }
        return null; // Return null if no match is found
    }

    public function findPlanByProductId(string $productId, bool $testmode = true ): ?array
    {
        $mode = $testmode ? 'testmode' : 'livemode';
        foreach ($this->plans as $planName => $planData) {
                if (!isset($planData[$mode]['products'])) {
                    continue;
                }
                
                foreach ($planData[$mode]['products'] as $key => $value) {
                    if ($key === $productId) {
                        $result = Array();
                        $result[$planName] = $planData[$mode];
                        $result[$planName]["config"] = $this->getConfig($result[$planName]["config"]);
                        return $result;
                    }
                }
        }
        
        return null; // Return null if no match is found
    }

    public function getConfig(string $configKey): array
    {
        if (!array_key_exists($configKey, $this->config)) {
            throw new \InvalidArgumentException("Config does not exist: " . $configKey);
        }
        return $this->config[$configKey];
    }
}