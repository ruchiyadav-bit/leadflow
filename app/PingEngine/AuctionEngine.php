<?php
declare(strict_types=1);

namespace LeadFlow\PingEngine;

use LeadFlow\Core\Config;

final class AuctionEngine
{
    public function __construct(private Config $config) {}

    /**
     * Return an ordered list of winners (highest bid first, applies fallback order).
     * $mode: 'highest_bid' | 'priority' | 'weighted' | 'round_robin'
     */
    public function selectOrder(array $pingResults, string $mode = 'highest_bid', float $minBid = 0.0): array
    {
        $eligible = array_values(array_filter($pingResults, fn($r) => $r['accepted'] === true && ($r['bid'] ?? 0) >= max($minBid, (float)$this->config->get('pingpost.auction_min_bid', 0.01))));

        $tieBreak = $this->config->get('pingpost.auction_tie_break', 'response_time');

        usort($eligible, function ($a, $b) use ($mode, $tieBreak) {
            if ($mode === 'priority') {
                $ap = (int)($a['buyer']['priority'] ?? 100);
                $bp = (int)($b['buyer']['priority'] ?? 100);
                if ($ap !== $bp) return $ap <=> $bp;
            }
            if ($mode === 'weighted') {
                $aw = ($a['bid'] ?? 0) * max(1, (int)($a['buyer']['weight'] ?? 1));
                $bw = ($b['bid'] ?? 0) * max(1, (int)($b['buyer']['weight'] ?? 1));
                if ($aw !== $bw) return $bw <=> $aw;
            }
            $bidCmp = ($b['bid'] ?? 0) <=> ($a['bid'] ?? 0);
            if ($bidCmp !== 0) return $bidCmp;
            if ($tieBreak === 'response_time') return $a['rt_ms'] <=> $b['rt_ms'];
            return ($a['buyer']['priority'] ?? 100) <=> ($b['buyer']['priority'] ?? 100);
        });

        if ($mode === 'round_robin' && !empty($eligible)) {
            usort($eligible, fn($a,$b) => strcmp((string)($a['buyer']['id'] ?? 0), (string)($b['buyer']['id'] ?? 0)));
            $idx = abs(crc32(date('YmdHi'))) % count($eligible);
            $eligible = array_merge(array_slice($eligible, $idx), array_slice($eligible, 0, $idx));
        }
        return $eligible;
    }
}
