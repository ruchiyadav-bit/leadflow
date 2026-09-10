<?php
declare(strict_types=1);

namespace LeadFlow\Services;

use LeadFlow\Repositories\BuyerRepository;

final class BuyerEligibilityService
{
    private RuleEngine $ruleEngine;

    public function __construct(private BuyerRepository $buyerRepo)
    {
        $this->ruleEngine = new RuleEngine();
    }

    /**
     * Return list of eligible buyer records for the given lead.
     * @param int[] $buyerIds Optional restriction (e.g. from ping tree)
     */
    public function eligibleBuyers(array $lead, array $buyerIds = []): array
    {
        $all = empty($buyerIds)
            ? $this->buyerRepo->all()
            : array_filter(array_map(fn($id) => $this->buyerRepo->findById((int)$id), $buyerIds));

        $eligible = [];
        foreach ($all as $b) {
            if (!$b || !(int)$b['active']) continue;
            $buyer = isset($b['field_map']) ? $b : $this->buyerRepo->findById((int)$b['id']);
            if (!$buyer) continue;

            if (!$this->scheduleAllows($buyer)) continue;
            if (!$this->capsAllow($buyer)) continue;
            $rules = json_decode($buyer['rules_json'] ?? '[]', true) ?: [];
            if (!$this->ruleEngine->evaluate($lead, $rules)) continue;

            $eligible[] = $buyer;
        }
        return $eligible;
    }

    private function scheduleAllows(array $buyer): bool
    {
        $schedule = json_decode($buyer['schedule_json'] ?? '[]', true) ?: [];
        if (empty($schedule)) return true;
        $tz = new \DateTimeZone($schedule['timezone'] ?? 'UTC');
        $now = new \DateTime('now', $tz);
        $dow = strtolower($now->format('D'));
        $days = array_map('strtolower', $schedule['days'] ?? []);
        if (!empty($days) && !in_array($dow, $days, true)) return false;
        if (!empty($schedule['start']) && !empty($schedule['end'])) {
            $start = \DateTime::createFromFormat('H:i', $schedule['start'], $tz);
            $end   = \DateTime::createFromFormat('H:i', $schedule['end'], $tz);
            if ($start && $end) {
                $nowT = (int)$now->format('Hi');
                $s = (int)$start->format('Hi');
                $e = (int)$end->format('Hi');
                if ($s <= $e) { if ($nowT < $s || $nowT > $e) return false; }
                else { if ($nowT < $s && $nowT > $e) return false; }
            }
        }
        return true;
    }

    private function capsAllow(array $buyer): bool
    {
        $counts = $this->buyerRepo->counts((int)$buyer['id']);
        if (!empty($buyer['hourly_cap'])  && $counts['hour']  >= (int)$buyer['hourly_cap']) return false;
        if (!empty($buyer['daily_cap'])   && $counts['today'] >= (int)$buyer['daily_cap']) return false;
        if (!empty($buyer['monthly_cap']) && $counts['month'] >= (int)$buyer['monthly_cap']) return false;
        if (!empty($buyer['total_cap'])   && $counts['total'] >= (int)$buyer['total_cap']) return false;
        return true;
    }
}
