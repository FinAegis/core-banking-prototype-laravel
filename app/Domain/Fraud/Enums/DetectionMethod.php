<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Enums;

enum DetectionMethod: string
{
    // Statistical methods
    case ZScore = 'z_score';
    case IQR = 'iqr';
    case IsolationForest = 'isolation_forest';
    case LOF = 'lof';

    // Behavioral methods
    case AdaptiveThreshold = 'adaptive_threshold';
    case DriftDetection = 'drift_detection';

    // Velocity methods
    case SlidingWindow = 'sliding_window';
    case BurstDetection = 'burst';

    // Geolocation methods
    case ImpossibleTravel = 'impossible_travel';
    case IpReputation = 'ip_reputation';
    case GeoClustering = 'geo_clustering';

    public function anomalyType(): AnomalyType
    {
        return match ($this) {
            self::ZScore, self::IQR, self::IsolationForest, self::LOF       => AnomalyType::Statistical,
            self::AdaptiveThreshold, self::DriftDetection                   => AnomalyType::Behavioral,
            self::SlidingWindow, self::BurstDetection                       => AnomalyType::Velocity,
            self::ImpossibleTravel, self::IpReputation, self::GeoClustering => AnomalyType::Geolocation,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ZScore            => 'Z-Score Analysis',
            self::IQR               => 'Interquartile Range',
            self::IsolationForest   => 'Isolation Forest',
            self::LOF               => 'Local Outlier Factor',
            self::AdaptiveThreshold => 'Adaptive Threshold',
            self::DriftDetection    => 'Drift Detection',
            self::SlidingWindow     => 'Sliding Window',
            self::BurstDetection    => 'Burst Detection',
            self::ImpossibleTravel  => 'Impossible Travel',
            self::IpReputation      => 'IP Reputation',
            self::GeoClustering     => 'Geo-Clustering',
        };
    }
}
