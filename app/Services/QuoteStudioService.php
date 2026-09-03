<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class QuoteStudioService
{
    public function __construct(private Database $db, private Auth $auth)
    {
    }

    public function builderData(int $proposalId = 0): array
    {
        $packages = $this->packagesByType('quote_setup');
        $supportPackages = $this->packagesByType('support_contract');

        $proposal = $proposalId > 0 ? $this->quote($proposalId) : null;
        $relationships = $this->relationshipOptions();

        return [
            'company' => config('quote_studio.company'),
            'locales' => config('quote_studio.locales'),
            'assessment' => config('quote_studio.assessment'),
            'recommendation' => config('quote_studio.recommendation'),
            'packages' => $packages,
            'supportPackages' => $supportPackages,
            'supportPlans' => $this->db->all('SELECT * FROM quote_support_plans WHERE active=1 ORDER BY sort_order,id'),
            'relationships' => $relationships,
            'clients' => array_values(array_filter($relationships, static fn(array $row): bool => $row['type'] === 'client')),
            'proposal' => $proposal,
        ];
    }

    public function savedQuotes(array $filters = []): array
    {
        $where = ['p.builder_version IS NOT NULL'];
        $parameters = [];
        if (($status = trim((string)($filters['status'] ?? ''))) !== '') {
            $where[] = 'p.status=?';
            $parameters[] = $status;
        }
        if (($query = trim((string)($filters['q'] ?? ''))) !== '') {
            $where[] = '(p.proposal_number LIKE ? OR p.business_name LIKE ? OR p.contact_name LIKE ? OR p.contact_email LIKE ?)';
            array_push($parameters, ...array_fill(0, 4, '%'.$query.'%'));
        }
        if (($packageId = (int)($filters['package_id'] ?? 0)) > 0) { $where[]='p.package_id=?'; $parameters[]=$packageId; }
        if (($dateFrom = trim((string)($filters['date_from'] ?? ''))) !== '') { $where[]='DATE(COALESCE(p.updated_at,p.created_at))>=?'; $parameters[]=$dateFrom; }
        if (($dateTo = trim((string)($filters['date_to'] ?? ''))) !== '') { $where[]='DATE(COALESCE(p.updated_at,p.created_at))<=?'; $parameters[]=$dateTo; }

        return $this->db->all('SELECT p.*,pk.name AS package_name,pk.tier AS package_tier FROM proposals p LEFT JOIN packages pk ON pk.id=p.package_id WHERE '.implode(' AND ', $where).' ORDER BY COALESCE(p.updated_at,p.created_at) DESC,p.id DESC', $parameters);
    }

    public function savedQuoteFilterOptions(): array
    {
        return ['packages'=>$this->db->all('SELECT DISTINCT pk.id,pk.name FROM proposals p JOIN packages pk ON pk.id=p.package_id WHERE p.builder_version IS NOT NULL ORDER BY pk.name')];
    }

    public function quote(int $id): ?array
    {
        $quote = $this->db->first('SELECT p.*,pk.name AS package_name,pk.name_ar AS package_name_ar,pk.name_he AS package_name_he,pk.description AS package_description,pk.description_ar AS package_description_ar,pk.description_he AS package_description_he,sp.name AS support_package_name,sp.name_ar AS support_package_name_ar,sp.name_he AS support_package_name_he,sp.description AS support_package_description,sp.description_ar AS support_package_description_ar,sp.description_he AS support_package_description_he FROM proposals p LEFT JOIN packages pk ON pk.id=p.package_id LEFT JOIN packages sp ON sp.id=p.support_package_id WHERE p.id=? AND p.builder_version IS NOT NULL', [$id]);
        if (! $quote) { return null; }
        $quote['assessment'] = json_decode((string)($quote['assessment_data'] ?? '{}'), true) ?: [];
        $quote['items'] = $this->db->all('SELECT * FROM proposal_items WHERE proposal_id=? ORDER BY sort_order,id', [$id]);
        $workItems = $this->db->all('SELECT * FROM proposal_work_items WHERE proposal_id=? ORDER BY sort_order,id', [$id]);
        $workItemsByProposalItem = [];
        foreach ($workItems as $workItem) { $workItemsByProposalItem[(int)$workItem['proposal_item_id']][] = $workItem; }
        foreach ($quote['items'] as &$item) { $item['work_items'] = $workItemsByProposalItem[(int)$item['id']] ?? []; }
        unset($item);
        $quote['deliveries'] = $this->db->all('SELECT * FROM proposal_deliveries WHERE proposal_id=? ORDER BY sent_at DESC,id DESC', [$id]);
        return $quote;
    }

    public function quoteByToken(string $token): ?array
    {
        $id=(int)($this->db->scalar('SELECT id FROM proposals WHERE share_token=? AND builder_version IS NOT NULL',[$token])?:0);
        return $id ? $this->quote($id) : null;
    }

    public function save(array $input): int
    {
        $businessName = trim((string)($input['business_name'] ?? ''));
        $contactName = trim((string)($input['contact_name'] ?? ''));
        $email = strtolower(trim((string)($input['contact_email'] ?? '')));
        if ($businessName === '' || $contactName === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Business name, contact name, and a valid email are required.');
        }

        $locale = in_array(($input['locale'] ?? 'en'), ['en','ar','he'], true) ? (string)$input['locale'] : 'en';
        $selectedTier = substr(trim((string)($input['selected_tier'] ?? 'basic')), 0, 20) ?: 'basic';
        $assessment = $this->decodeJson($input['assessment_json'] ?? '{}', 'assessment');
        $items = $this->decodeJson($input['items_json'] ?? '[]', 'service selection');
        $supportItems = $this->decodeJson($input['support_items_json'] ?? '[]', 'support contract selection');
        if (! is_array($items) || count($items) === 0) { throw new InvalidArgumentException('Select at least one setup service.'); }

        $score = $this->assessmentScore($assessment);
        $recommendedTier = $score <= (int)config('quote_studio.recommendation.basic_max') ? 'basic' : ($score <= (int)config('quote_studio.recommendation.medium_max') ? 'medium' : 'pro');
        $package = $this->db->first("SELECT id FROM packages WHERE package_type='quote_setup' AND tier=? AND active=1 LIMIT 1", [$selectedTier]);
        if (! $package) { throw new InvalidArgumentException('The selected setup tier is not available.'); }

        [$normalizedItems,$setupSubtotal] = $this->normalizePackageSelections($items, (int)$package['id'], 'service', 1);
        if (! $normalizedItems) { throw new InvalidArgumentException('The selected services are no longer available.'); }

        $support = $this->db->first('SELECT * FROM quote_support_plans WHERE code=? AND active=1', [(string)($input['support_plan'] ?? 'none')]) ?: $this->db->first("SELECT * FROM quote_support_plans WHERE code='none'");
        $supportPackageId = max(0, (int)($input['support_package_id'] ?? 0));
        $legacyMembershipCode = trim((string)($input['membership_plan'] ?? 'none'));
        if (! $supportPackageId && ! in_array($legacyMembershipCode, ['', 'none', 'custom'], true)) {
            $supportPackageId = (int)($this->db->scalar("SELECT id FROM packages WHERE package_type='support_contract' AND tier=? AND active=1 LIMIT 1", [$legacyMembershipCode]) ?: 0);
        }
        $supportPackage = $supportPackageId ? $this->db->first("SELECT * FROM packages WHERE id=? AND package_type='support_contract' AND active=1", [$supportPackageId]) : null;
        if ($supportPackageId && ! $supportPackage) { throw new InvalidArgumentException('The selected support contract package is not available.'); }
        if ($supportPackage && (! is_array($supportItems) || ! $supportItems)) { $supportItems=$this->defaultPackageSelections($supportPackageId); }
        [$normalizedSupportItems,$membershipMonthlyPrice] = $supportPackage
            ? $this->normalizePackageSelections(is_array($supportItems) ? $supportItems : [], $supportPackageId, 'support_contract', 1000)
            : [[],0.0];
        if ($supportPackage && ! $normalizedSupportItems) { throw new InvalidArgumentException('Select at least one support contract service.'); }
        $legacyCustomMembership = ! $supportPackage && $legacyMembershipCode === 'custom';
        $customSupport = (string)$support['code'] === 'custom';
        $supportName = $customSupport ? (trim((string)($input['custom_support_name'] ?? '')) ?: 'Custom support') : (string)$support['name'];
        $supportDuration = (string)$support['code'] === 'none' ? 0 : ($customSupport ? min(60, max(1, (int)($input['custom_support_duration'] ?? 3))) : (int)$support['duration_months']);
        $supportRatePercent = $customSupport ? min(100, max(0, (float)($input['custom_support_rate_percent'] ?? 0))) : (float)$support['rate_percent'];
        $supportMultiplier = $customSupport ? $supportDuration / 3 : (float)$support['multiplier'];
        $supportAmount = round($setupSubtotal * ($supportRatePercent / 100) * $supportMultiplier, 2);
        $membershipDuration = ($supportPackage || $legacyCustomMembership) ? min(60, max(1, (int)($input['membership_term'] ?? 3))) : 0;
        $membershipName = $supportPackage ? (string)$supportPackage['name'] : ($legacyCustomMembership ? (trim((string)($input['custom_membership_name']??'')) ?: 'Custom support contract') : null);
        if ($legacyCustomMembership) { $membershipMonthlyPrice=round(max(0,(float)($input['custom_membership_monthly_price']??0)),2); }
        $membershipAmount = round($membershipMonthlyPrice * $membershipDuration, 2);
        $subtotal = round($setupSubtotal + $supportAmount + $membershipAmount, 2);
        $taxPercent = min(100, max(0, (float)($input['tax_percent'] ?? 13)));
        $taxAmount = ! empty($input['tax_enabled']) ? round($subtotal * $taxPercent / 100, 2) : 0.0;
        $total = round($subtotal + $taxAmount, 2);
        $validityDays = min(90, max(1, (int)($input['validity_days'] ?? 7)));
        $proposalId = max(0, (int)($input['proposal_id'] ?? 0));
        $existing = $proposalId ? $this->quote($proposalId) : null;
        if ($proposalId && ! $existing) { throw new InvalidArgumentException('The quote could not be found.'); }
        if ($existing && ($existing['status'] ?? '') === 'accepted') { throw new InvalidArgumentException('Accepted quotes are locked. Create a new revision to make changes.'); }

        return $this->db->transaction(function () use ($input,$businessName,$contactName,$email,$locale,$selectedTier,$assessment,$score,$recommendedTier,$package,$normalizedItems,$normalizedSupportItems,$setupSubtotal,$support,$customSupport,$supportName,$supportDuration,$supportRatePercent,$supportAmount,$supportPackage,$supportPackageId,$legacyCustomMembership,$membershipName,$membershipDuration,$membershipMonthlyPrice,$membershipAmount,$subtotal,$taxPercent,$taxAmount,$total,$validityDays,$proposalId,$existing): int {
            $now = date('c');
            [$leadId,$clientId,$opportunityId] = $this->relationshipIds($input, $existing, $businessName, $contactName, $email, $total, $score);
            $values = [
                'client_id'=>$clientId,'lead_id'=>$leadId,'opportunity_id'=>$opportunityId,'package_id'=>(int)$package['id'],'support_package_id'=>$supportPackageId ?: null,'builder_version'=>1,'locale'=>$locale,
                'title'=>$businessName.' proposal','business_name'=>$businessName,'contact_name'=>$contactName,'contact_email'=>$email,
                'contact_phone'=>trim((string)($input['contact_phone'] ?? '')) ?: null,'website'=>trim((string)($input['website'] ?? '')) ?: null,
                'business_stage'=>in_array(($input['business_stage'] ?? ''), ['new_business','existing_business'], true) ? $input['business_stage'] : null,
                'years_operating'=>max(0, (int)($input['years_operating'] ?? 0)),'summary'=>trim((string)($input['summary'] ?? '')) ?: null,
                'internal_notes'=>trim((string)($input['internal_notes'] ?? '')) ?: null,'assessment_data'=>json_encode($assessment, JSON_UNESCAPED_UNICODE),
                'assessment_score'=>$score,'recommended_tier'=>$recommendedTier,'selected_tier'=>$selectedTier,'support_plan'=>$support['code'],'support_name'=>$supportDuration?$supportName:null,'support_duration_months'=>$supportDuration,'support_rate_percent'=>$supportRatePercent,'support_amount'=>$supportAmount,
                'membership_plan'=>$supportPackage['tier']??($legacyCustomMembership?'custom':'none'),'membership_name'=>$membershipDuration ? $membershipName : null,'membership_duration_months'=>$membershipDuration,'membership_monthly_price'=>$membershipMonthlyPrice,'membership_amount'=>$membershipAmount,'currency'=>'CAD','subtotal'=>$subtotal,'discount'=>max(0, array_sum(array_map(fn(array $row): float => max(0, (float)$row['unit_price'] - (float)$row['total']), array_merge($normalizedItems,$normalizedSupportItems)))),
                'tax_percent'=>$taxPercent,'tax'=>$taxAmount,'total'=>$total,'deposit'=>0,'valid_until'=>date('Y-m-d', strtotime('+'.$validityDays.' days')),
                'validity_days'=>$validityDays,'updated_at'=>$now,
            ];
            if ($existing) {
                $this->db->update('proposals', $proposalId, $values);
                $this->db->execute('DELETE FROM proposal_items WHERE proposal_id=?', [$proposalId]);
            } else {
                $values += ['proposal_number'=>$this->nextNumber(),'status'=>'draft','revision'=>1,'sent_count'=>0,'share_token'=>Str::random(48),'created_at'=>$now];
                $proposalId = $this->db->insert('proposals', $values);
            }
            foreach ($normalizedItems as $row) {
                $workItems = $row['work_items'];
                unset($row['work_items']);
                $proposalItemId = $this->db->insert('proposal_items', ['proposal_id'=>$proposalId] + $row);
                $this->snapshotWorkItems($proposalId, $proposalItemId, (int)$row['service_id'], (int)$package['id'], $workItems);
            }
            if ((string)$support['code'] !== 'none') {
                $rateLabel = rtrim(rtrim(number_format($supportRatePercent, 2), '0'), '.');
                $description = $customSupport ? 'Technical support for '.$supportDuration.' months at '.$rateLabel.'% of the setup subtotal per 3 months.' : (string)$support['description'];
                $descriptionAr = $customSupport ? 'دعم فني لمدة '.$supportDuration.' أشهر بنسبة '.$rateLabel.'% من مجموع الإعداد لكل 3 أشهر.' : (string)$support['description_ar'];
                $descriptionHe = $customSupport ? 'תמיכה טכנית למשך '.$supportDuration.' חודשים בשיעור '.$rateLabel.'% מסכום ההקמה לכל 3 חודשים.' : (string)$support['description_he'];
                $this->db->insert('proposal_items', ['proposal_id'=>$proposalId,'service_id'=>null,'item_type'=>'support','title'=>$supportName,'title_ar'=>$customSupport?$supportName:$support['name_ar'],'title_he'=>$customSupport?$supportName:$support['name_he'],'description'=>$description,'description_ar'=>$descriptionAr,'description_he'=>$descriptionHe,'category'=>'Technical Support','quantity'=>1,'unit_price'=>$supportAmount,'discount_percent'=>0,'total'=>$supportAmount,'sort_order'=>900]);
            }
            foreach ($normalizedSupportItems as $row) {
                $workItems = $row['work_items'];
                unset($row['work_items']);
                $row['quantity'] = $membershipDuration;
                $row['total'] = round((float)$row['total'] * $membershipDuration, 2);
                $proposalItemId = $this->db->insert('proposal_items', ['proposal_id'=>$proposalId] + $row);
                $this->snapshotWorkItems($proposalId, $proposalItemId, (int)$row['service_id'], $supportPackageId, $workItems);
            }
            if ($legacyCustomMembership) {
                $description='Custom support contract at $'.number_format($membershipMonthlyPrice,2).'/month for '.$membershipDuration.' months.';
                $this->db->insert('proposal_items',['proposal_id'=>$proposalId,'service_id'=>null,'item_type'=>'membership','title'=>$membershipName,'title_ar'=>$membershipName,'title_he'=>$membershipName,'description'=>$description,'description_ar'=>$description,'description_he'=>$description,'category'=>'Support Contract','quantity'=>$membershipDuration,'unit_price'=>$membershipMonthlyPrice,'discount_percent'=>0,'total'=>$membershipAmount,'sort_order'=>1000]);
            }
            $this->audit('quote.saved', 'Quote '.$proposalId.' saved for '.$businessName, 'proposal', $proposalId);
            return $proposalId;
        });
    }

    public function duplicate(int $id): int
    {
        $quote = $this->quote($id);
        if (! $quote) { throw new InvalidArgumentException('The quote could not be found.'); }
        return $this->db->transaction(function () use ($quote,$id): int {
            $copy = $quote;
            foreach (['id','items','deliveries','assessment','package_name','package_name_ar','package_name_he','package_description','package_description_ar','package_description_he','support_package_name','support_package_name_ar','support_package_name_he','support_package_description','support_package_description_ar','support_package_description_he'] as $key) { unset($copy[$key]); }
            $copy['proposal_number'] = $this->nextNumber();
            $copy['parent_proposal_id'] = $id;
            $copy['revision'] = (int)$this->db->scalar('SELECT COALESCE(MAX(revision),0)+1 FROM proposals WHERE id=? OR parent_proposal_id=?', [$id,$id]);
            $copy['status'] = 'draft'; $copy['sent_count'] = 0; $copy['last_sent_at'] = null; $copy['share_token'] = Str::random(48); $copy['created_at'] = date('c'); $copy['updated_at'] = date('c');
            $newId = $this->db->insert('proposals', $copy);
            foreach ($quote['items'] as $item) {
                $workItems = $item['work_items'] ?? [];
                unset($item['id'], $item['work_items']);
                $item['proposal_id']=$newId;
                $newProposalItemId = $this->db->insert('proposal_items', $item);
                foreach ($workItems as $workItem) {
                    unset($workItem['id']);
                    $workItem['proposal_id'] = $newId;
                    $workItem['proposal_item_id'] = $newProposalItemId;
                    $this->db->insert('proposal_work_items', $workItem);
                }
            }
            $this->audit('quote.duplicated', 'Quote '.$id.' duplicated as '.$newId, 'proposal', $newId);
            return $newId;
        });
    }

    public function deleteQuote(int $id): int
    {
        $quote = $this->quote($id);
        if (! $quote) { throw new InvalidArgumentException('The quote could not be found.'); }
        if (($quote['status'] ?? '') === 'accepted') {
            throw new InvalidArgumentException('Accepted and onboarded quotes cannot be deleted. Create a new revision if another quote is required.');
        }

        return $this->db->transaction(function () use ($id, $quote): int {
            // Keep later revisions valid when their original quote is removed.
            $this->db->execute('UPDATE proposals SET parent_proposal_id=NULL WHERE parent_proposal_id=?', [$id]);
            $deleted = $this->db->execute('DELETE FROM proposals WHERE id=? AND builder_version IS NOT NULL', [$id]);
            if ($deleted !== 1) { throw new InvalidArgumentException('The quote could not be deleted.'); }

            // Proposal items and delivery records are removed by their cascading foreign keys.
            $this->audit('quote.deleted', 'Quote '.$quote['proposal_number'].' deleted for '.$quote['business_name'], 'proposal', $id);
            return $id;
        });
    }

    public function recordDelivery(array $input): int
    {
        $id = (int)($input['proposal_id'] ?? 0);
        $quote = $this->quote($id);
        if (! $quote) { throw new InvalidArgumentException('The quote could not be found.'); }
        $recipient = strtolower(trim((string)($input['recipient_email'] ?? $quote['contact_email'])));
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('A valid recipient email is required.'); }
        $locale = in_array(($input['locale'] ?? $quote['locale']), ['en','ar','he'], true) ? (string)($input['locale'] ?? $quote['locale']) : 'en';
        $now = date('c');
        $deliveryId = $this->db->insert('proposal_deliveries', ['proposal_id'=>$id,'recipient_email'=>$recipient,'channel'=>(string)($input['channel'] ?? 'email'),'locale'=>$locale,'status'=>'recorded','sent_at'=>$now,'notes'=>trim((string)($input['delivery_notes'] ?? '')) ?: null]);
        $this->db->execute("UPDATE proposals SET status='sent',sent_count=sent_count+1,last_sent_at=?,updated_at=? WHERE id=?", [$now,$now,$id]);
        $this->audit('quote.sent', 'Quote '.$id.' delivery recorded for '.$recipient, 'proposal', $id);
        return $deliveryId;
    }

    public function onboard(int $id): int
    {
        $quote = $this->quote($id);
        if (! $quote) { throw new InvalidArgumentException('The quote could not be found.'); }
        $clientId = (int)($quote['client_id'] ?? 0);
        if (! $clientId) {
            if (empty($quote['lead_id'])) { throw new InvalidArgumentException('This quote is not linked to a prospect.'); }
            $lead = $this->db->first('SELECT converted_client_id FROM leads WHERE id=?', [(int)$quote['lead_id']]);
            if (! $lead) { throw new InvalidArgumentException('The linked prospect no longer exists.'); }
            $clientId = ! empty($lead['converted_client_id']) ? (int)$lead['converted_client_id'] : (new AgencyService($this->db, $this->auth))->convertLead((int)$quote['lead_id']);
        }
        $this->db->transaction(function () use ($quote, $clientId, $id): void {
            $this->db->execute("UPDATE proposals SET client_id=?,status='accepted',updated_at=? WHERE id=?", [$clientId,date('c'),$id]);
            $this->createDeliveryProjects($quote, $clientId);
        });
        try {
            (new AgencyService($this->db, $this->auth))->ensureClientPortalAccess($clientId);
        } catch (InvalidArgumentException $exception) {
            $this->audit('client.portal_provisioning_skipped', $exception->getMessage(), 'client', $clientId);
        }
        $this->audit('quote.onboarded', 'Quote '.$id.' accepted and client '.$clientId.' onboarded', 'proposal', $id);
        return $clientId;
    }

    public function settingsData(): array
    {
        $packages = $this->packagesByType('quote_setup');
        $supportPackages = $this->packagesByType('support_contract');
        $services = $this->db->all('SELECT s.*,sc.name AS category_name FROM services s JOIN service_categories sc ON sc.id=s.category_id ORDER BY s.quote_sort,s.name');
        $workItems = $this->db->all('SELECT * FROM service_work_items ORDER BY service_id,sort_order,id');
        $rules = $this->db->all('SELECT * FROM package_service_item_rules ORDER BY package_id,id');
        $rulesByWorkItem = [];
        foreach ($rules as $rule) { $rulesByWorkItem[(int)$rule['service_work_item_id']][(int)$rule['package_id']] = $rule; }
        $workItemsByService = [];
        foreach ($workItems as $workItem) {
            $workItem['package_rules'] = $rulesByWorkItem[(int)$workItem['id']] ?? [];
            $workItemsByService[(int)$workItem['service_id']][] = $workItem;
        }
        foreach ($services as &$service) { $service['work_items'] = $workItemsByService[(int)$service['id']] ?? []; }
        unset($service);
        return [
            'services'=>$services,
            'categories'=>$this->db->all('SELECT id,name FROM service_categories WHERE active=1 ORDER BY name'),
            'packages'=>$packages,
            'supportPackages'=>$supportPackages,
            'supportPlans'=>$this->db->all('SELECT * FROM quote_support_plans ORDER BY sort_order,id'),
        ];
    }

    public function saveService(array $input): int
    {
        $name = trim((string)($input['name'] ?? ''));
        $categoryId = (int)($input['category_id'] ?? 0);
        if ($name === '' || ! $this->db->scalar('SELECT id FROM service_categories WHERE id=?', [$categoryId])) { throw new InvalidArgumentException('Service name and category are required.'); }
        $values = ['category_id'=>$categoryId,'name'=>$name,'name_ar'=>trim((string)($input['name_ar'] ?? '')) ?: null,'name_he'=>trim((string)($input['name_he'] ?? '')) ?: null,'description'=>trim((string)($input['description'] ?? '')) ?: null,'description_ar'=>trim((string)($input['description_ar'] ?? '')) ?: null,'description_he'=>trim((string)($input['description_he'] ?? '')) ?: null,'pricing_type'=>(string)($input['pricing_type'] ?? 'one_time'),'default_price'=>max(0,(float)($input['default_price'] ?? 0)),'cost_estimate'=>max(0,(float)($input['cost_estimate'] ?? 0)),'estimated_hours'=>max(0,(float)($input['estimated_hours'] ?? 0)),'taxable'=>!empty($input['taxable'])?1:0,'active'=>!empty($input['active'])?1:0,'quote_enabled'=>!empty($input['quote_enabled'])?1:0,'quote_sort'=>max(0,(int)($input['quote_sort'] ?? 0))];
        $id = $this->db->transaction(function () use ($input, $values): int {
            $id = (int)($input['service_id'] ?? 0);
            if ($id) {
                if (! $this->db->scalar('SELECT id FROM services WHERE id=?', [$id])) { throw new InvalidArgumentException('Service not found.'); }
                $this->db->update('services',$id,$values);
            } else {
                $created = $values;
                $created['created_at']=date('c');
                $id=$this->db->insert('services',$created);
            }
            if (! empty($input['work_items_present'])) { $this->syncServiceWorkItems($id, (array)($input['work_items'] ?? [])); }
            return $id;
        });
        $this->audit('quote.service_saved','Quote service '.$id.' saved','service',$id);
        return $id;
    }

    public function savePackage(array $input): int
    {
        $id=(int)($input['package_id']??0);
        $requestedPackageType=(string)($input['package_type']??'quote_setup');
        $packageType=in_array($requestedPackageType, ['quote_setup','support_contract'], true)?$requestedPackageType:'quote_setup';
        $typeLabel=$packageType==='support_contract'?'Support contract':'Setup';
        $name=trim((string)($input['name']??''));
        if($name===''){throw new InvalidArgumentException('Package name is required.');}
        $tier=substr(Str::slug(trim((string)($input['tier']??$name)),'_'),0,20);
        if($tier===''){$tier='package';}
        if($this->db->scalar('SELECT id FROM packages WHERE package_type=? AND tier=? AND id<>?',[$packageType,$tier,$id])){throw new InvalidArgumentException('That package code is already in use for this package type.');}
        $values=['name'=>$name,'name_ar'=>trim((string)($input['name_ar']??''))?:null,'name_he'=>trim((string)($input['name_he']??''))?:null,'tier'=>$tier,'description'=>trim((string)($input['description']??''))?:null,'description_ar'=>trim((string)($input['description_ar']??''))?:null,'description_he'=>trim((string)($input['description_he']??''))?:null,'display_order'=>max(1,(int)($input['display_order']??99)),'active'=>!empty($input['active'])?1:0,'updated_at'=>date('c')];
        if($id){
            if(!$this->db->scalar('SELECT id FROM packages WHERE id=? AND package_type=?',[$id,$packageType])){throw new InvalidArgumentException($typeLabel.' package not found.');}
            $this->db->update('packages',$id,$values);
        }else{
            $values+=['package_type'=>$packageType,'featured'=>0,'created_at'=>date('c')];
            $id=$this->db->insert('packages',$values);
        }
        $this->audit('quote.package_saved','Quote package '.$id.' saved','package',$id);
        return $id;
    }

    public function addPackageItem(array $input): int
    {
        $packageId=(int)($input['package_id']??0);$serviceId=(int)($input['service_id']??0);
        if(!$this->db->scalar("SELECT id FROM packages WHERE id=? AND package_type IN ('quote_setup','support_contract')",[$packageId])){throw new InvalidArgumentException('Quote package not found.');}
        $service=$this->db->first('SELECT id,description,description_ar,description_he,default_price,cost_estimate,estimated_hours FROM services WHERE id=? AND active=1 AND quote_enabled=1',[$serviceId]);
        if(!$service){throw new InvalidArgumentException('Select an active Quote Studio service.');}
        if($this->db->scalar('SELECT id FROM package_items WHERE package_id=? AND service_id=?',[$packageId,$serviceId])){throw new InvalidArgumentException('That service is already in this package.');}
        $description=trim((string)($input['description']??''))?:$service['description'];
        $id=$this->db->transaction(function () use ($input,$packageId,$serviceId,$service,$description): int {
            $id=$this->db->insert('package_items',['package_id'=>$packageId,'service_id'=>$serviceId,'quantity'=>1,'unit_price'=>max(0,(float)($input['unit_price']??$service['default_price'])),'scope_note'=>$description,'description'=>$description,'description_ar'=>trim((string)($input['description_ar']??''))?:$service['description_ar'],'description_he'=>trim((string)($input['description_he']??''))?:$service['description_he'],'included'=>!empty($input['included'])?1:0,'sort_order'=>max(0,(int)($input['sort_order']??99)),'estimated_cost'=>$service['cost_estimate'],'estimated_hours'=>$service['estimated_hours']]);
            $this->syncPackageWorkItems($packageId, $serviceId, (array)($input['work_items'] ?? []));
            return $id;
        });
        $this->audit('quote.package_item_added','Service '.$serviceId.' added to package '.$packageId,'package_item',$id);
        return $id;
    }

    public function savePackageItem(array $input): int
    {
        $id=(int)($input['package_item_id']??0);
        $packageItem=$this->db->first('SELECT id,package_id,service_id FROM package_items WHERE id=?',[$id]);
        if(!$packageItem){throw new InvalidArgumentException('Package item not found.');}
        $this->db->transaction(function () use ($input,$id,$packageItem): void {
            $this->db->update('package_items',$id,['unit_price'=>max(0,(float)($input['unit_price']??0)),'description'=>trim((string)($input['description']??''))?:null,'description_ar'=>trim((string)($input['description_ar']??''))?:null,'description_he'=>trim((string)($input['description_he']??''))?:null,'included'=>!empty($input['included'])?1:0,'sort_order'=>max(0,(int)($input['sort_order']??0))]);
            if (! empty($input['work_items_present'])) {
                $this->syncPackageWorkItems((int)$packageItem['package_id'], (int)$packageItem['service_id'], (array)($input['work_items'] ?? []));
            }
        });
        $this->audit('quote.package_item_saved','Package item '.$id.' saved','package_item',$id);
        return $id;
    }

    public function removePackageItem(int $id): int
    {
        if(!$this->db->scalar('SELECT id FROM package_items WHERE id=?',[$id])){throw new InvalidArgumentException('Package item not found.');}
        $this->db->execute('DELETE FROM package_items WHERE id=?',[$id]);
        $this->audit('quote.package_item_removed','Package item '.$id.' removed','package_item',$id);
        return $id;
    }

    public function savePlan(array $input): int
    {
        $type=(string)($input['plan_type']??''); $id=(int)($input['plan_id']??0);
        if($type==='support'){
            if(!$this->db->scalar('SELECT id FROM quote_support_plans WHERE id=?',[$id])){throw new InvalidArgumentException('Support plan not found.');}
            $this->db->update('quote_support_plans',$id,['name'=>trim((string)$input['name']),'name_ar'=>trim((string)($input['name_ar']??''))?:null,'name_he'=>trim((string)($input['name_he']??''))?:null,'description'=>trim((string)($input['description']??''))?:null,'description_ar'=>trim((string)($input['description_ar']??''))?:null,'description_he'=>trim((string)($input['description_he']??''))?:null,'rate_percent'=>max(0,(float)($input['rate_percent']??0)),'multiplier'=>max(0,(float)($input['multiplier']??0)),'duration_months'=>max(0,(int)($input['duration_months']??0)),'active'=>!empty($input['active'])?1:0]);
        } elseif($type==='membership') {
            if(!$this->db->scalar('SELECT id FROM quote_memberships WHERE id=?',[$id])){throw new InvalidArgumentException('Membership plan not found.');}
            $this->db->update('quote_memberships',$id,['name'=>trim((string)$input['name']),'name_ar'=>trim((string)($input['name_ar']??''))?:null,'name_he'=>trim((string)($input['name_he']??''))?:null,'description'=>trim((string)($input['description']??''))?:null,'description_ar'=>trim((string)($input['description_ar']??''))?:null,'description_he'=>trim((string)($input['description_he']??''))?:null,'monthly_price'=>max(0,(float)($input['monthly_price']??0)),'featured'=>!empty($input['featured'])?1:0,'active'=>!empty($input['active'])?1:0]);
        } else { throw new InvalidArgumentException('Unknown plan type.'); }
        $this->audit('quote.plan_saved',ucfirst($type).' plan '.$id.' saved','quote_plan',$id);
        return $id;
    }

    private function resolvedWorkItems(int $serviceId, int $packageId): array
    {
        return $this->db->all(
            'SELECT wi.id,wi.service_id,COALESCE(r.name_override,wi.name) AS name,COALESCE(r.name_ar_override,wi.name_ar) AS name_ar,'.
            'COALESCE(r.name_he_override,wi.name_he) AS name_he,COALESCE(r.description_override,wi.task_description) AS task_description,'.
            'COALESCE(r.description_ar_override,wi.task_description_ar) AS task_description_ar,COALESCE(r.description_he_override,wi.task_description_he) AS task_description_he,'.
            'COALESCE(r.included,0) AS included,r.value_label,r.value_label_ar,r.value_label_he,'.
            'COALESCE(r.schedule_type,wi.schedule_type) AS schedule_type,COALESCE(r.frequency,wi.frequency) AS frequency,'.
            'COALESCE(r.interval_count,wi.interval_count) AS interval_count,COALESCE(r.starts_after_days,wi.starts_after_days) AS starts_after_days,'.
            'COALESCE(r.due_after_days,wi.due_after_days) AS due_after_days,COALESCE(r.occurrence_count,wi.occurrence_count) AS occurrence_count,'.
            'COALESCE(r.end_after_months,wi.end_after_months) AS end_after_months,COALESCE(r.estimated_hours,wi.estimated_hours) AS estimated_hours,'.
            'COALESCE(r.sort_order,wi.sort_order) AS sort_order FROM service_work_items wi LEFT JOIN package_service_item_rules r ON r.service_work_item_id=wi.id AND r.package_id=? '.
            'WHERE wi.service_id=? AND wi.active=1 ORDER BY COALESCE(r.sort_order,wi.sort_order),wi.id',
            [$packageId,$serviceId]
        );
    }

    private function packagesByType(string $packageType): array
    {
        $packages = $this->db->all(
            'SELECT p.*,pp.base_price FROM packages p LEFT JOIN package_pricing pp ON pp.package_id=p.id AND pp.effective_to IS NULL WHERE p.package_type=? AND p.active=1 ORDER BY p.display_order,p.id',
            [$packageType]
        );
        foreach ($packages as &$package) {
            $package['items'] = $this->db->all(
                'SELECT pi.*,s.name,s.name_ar,s.name_he,s.description AS service_description,s.description_ar AS service_description_ar,s.description_he AS service_description_he,sc.name AS category FROM package_items pi JOIN services s ON s.id=pi.service_id JOIN service_categories sc ON sc.id=s.category_id WHERE pi.package_id=? AND s.active=1 AND s.quote_enabled=1 ORDER BY pi.sort_order,pi.id',
                [(int)$package['id']]
            );
            foreach ($package['items'] as &$item) { $item['work_items']=$this->resolvedWorkItems((int)$item['service_id'], (int)$package['id']); }
            unset($item);
        }
        unset($package);
        return $packages;
    }

    private function normalizePackageSelections(array $items, int $packageId, string $itemType, int $sortOffset): array
    {
        $normalized = [];
        $subtotal = 0.0;
        foreach ($items as $index => $item) {
            if (! is_array($item) || empty($item['service_id'])) { continue; }
            $catalog = $this->db->first(
                'SELECT s.id,s.name,s.name_ar,s.name_he,s.description,s.description_ar,s.description_he,sc.name AS category FROM services s JOIN service_categories sc ON sc.id=s.category_id JOIN package_items pi ON pi.service_id=s.id AND pi.package_id=? WHERE s.id=? AND s.active=1 AND s.quote_enabled=1',
                [$packageId,(int)$item['service_id']]
            );
            if (! $catalog) { continue; }
            $listPrice=max(0,(float)($item['list_price']??0));
            $discount=min(100,max(0,(float)($item['discount_percent']??0)));
            $customPrice=($item['custom_price']??'')!==''&&($item['custom_price']??null)!==null?max(0,(float)$item['custom_price']):null;
            $finalPrice=$customPrice??round($listPrice*(1-$discount/100),2);
            $normalized[]=[
                'service_id'=>(int)$catalog['id'],'item_type'=>$itemType,'title'=>$catalog['name'],'title_ar'=>$catalog['name_ar'],'title_he'=>$catalog['name_he'],
                'description'=>trim((string)($item['description']??$catalog['description']??'')),'description_ar'=>trim((string)($item['description_ar']??$catalog['description_ar']??'')),'description_he'=>trim((string)($item['description_he']??$catalog['description_he']??'')),
                'category'=>$itemType==='support_contract'?'Support Contract · '.$catalog['category']:$catalog['category'],
                'quantity'=>1,'unit_price'=>$listPrice,'discount_percent'=>$discount,'total'=>$finalPrice,'sort_order'=>$sortOffset+$index,
                'work_items'=>$this->normalizeQuoteWorkItems((array)($item['work_items']??[]),(int)$catalog['id'],$packageId),
            ];
            $subtotal+=$finalPrice;
        }
        return [$normalized,round($subtotal,2)];
    }

    private function defaultPackageSelections(int $packageId): array
    {
        $items=$this->db->all('SELECT service_id,unit_price,description,description_ar,description_he FROM package_items WHERE package_id=? AND included=1 ORDER BY sort_order,id',[$packageId]);
        foreach($items as &$item){
            $item['list_price']=$item['unit_price'];
            $item['discount_percent']=0;
            $item['custom_price']=null;
            $item['work_items']=$this->resolvedWorkItems((int)$item['service_id'],$packageId);
        }
        unset($item);
        return $items;
    }

    private function snapshotWorkItems(int $proposalId, int $proposalItemId, int $serviceId, int $packageId, array $submittedItems = []): void
    {
        $workItems = $submittedItems ?: $this->normalizeQuoteWorkItems([], $serviceId, $packageId);
        foreach ($workItems as $workItem) {
            if (! (int)$workItem['included']) { continue; }
            $this->db->insert('proposal_work_items', [
                'proposal_id'=>$proposalId,
                'proposal_item_id'=>$proposalItemId,
                'service_work_item_id'=>(int)($workItem['service_work_item_id'] ?? $workItem['id']),
                'service_id'=>$serviceId,
                'title'=>$workItem['title'] ?? $workItem['name'] ?? 'Service item',
                'title_ar'=>$workItem['title_ar'] ?? $workItem['name_ar'] ?? null,
                'title_he'=>$workItem['title_he'] ?? $workItem['name_he'] ?? null,
                'description'=>$workItem['description'] ?? $workItem['task_description'] ?? null,
                'description_ar'=>$workItem['description_ar'] ?? $workItem['task_description_ar'] ?? null,
                'description_he'=>$workItem['description_he'] ?? $workItem['task_description_he'] ?? null,
                'scope_value'=>$workItem['scope_value'] ?? $workItem['value_label'] ?? null,
                'scope_value_ar'=>$workItem['scope_value_ar'] ?? $workItem['value_label_ar'] ?? null,
                'scope_value_he'=>$workItem['scope_value_he'] ?? $workItem['value_label_he'] ?? null,
                'schedule_type'=>$workItem['schedule_type'],
                'frequency'=>$workItem['schedule_type'] === 'recurring' ? $workItem['frequency'] : null,
                'interval_count'=>max(1,(int)$workItem['interval_count']),
                'starts_after_days'=>max(0,(int)$workItem['starts_after_days']),
                'due_after_days'=>max(0,(int)$workItem['due_after_days']),
                'occurrence_count'=>$workItem['occurrence_count'] !== null ? max(1,(int)$workItem['occurrence_count']) : null,
                'end_after_months'=>$workItem['end_after_months'] !== null ? max(1,(int)$workItem['end_after_months']) : null,
                'estimated_hours'=>max(0,(float)$workItem['estimated_hours']),
                'sort_order'=>(int)$workItem['sort_order'],
            ]);
        }
    }

    private function syncServiceWorkItems(int $serviceId, array $submittedItems): void
    {
        $existingIds = array_map('intval', array_column($this->db->all('SELECT id FROM service_work_items WHERE service_id=?', [$serviceId]), 'id'));
        $keptIds = [];
        $allowedSchedules = ['one_time','recurring'];
        $allowedFrequencies = ['daily','weekly','monthly','yearly'];
        $now = date('c');

        foreach ($submittedItems as $position => $submitted) {
            if (! is_array($submitted)) { continue; }
            $name = trim((string)($submitted['name'] ?? ''));
            if ($name === '') { throw new InvalidArgumentException('Every service item requires an English name.'); }
            $schedule = in_array(($submitted['schedule_type'] ?? ''), $allowedSchedules, true) ? (string)$submitted['schedule_type'] : 'one_time';
            $frequency = in_array(($submitted['frequency'] ?? ''), $allowedFrequencies, true) ? (string)$submitted['frequency'] : null;
            if ($schedule === 'recurring' && ! $frequency) { throw new InvalidArgumentException($name.' requires a recurring frequency.'); }

            $workItemId = (int)($submitted['id'] ?? 0);
            $values = [
                'service_id'=>$serviceId,
                'name'=>$name,
                'name_ar'=>trim((string)($submitted['name_ar'] ?? '')) ?: null,
                'name_he'=>trim((string)($submitted['name_he'] ?? '')) ?: null,
                'task_description'=>trim((string)($submitted['task_description'] ?? '')) ?: null,
                'task_description_ar'=>trim((string)($submitted['task_description_ar'] ?? '')) ?: null,
                'task_description_he'=>trim((string)($submitted['task_description_he'] ?? '')) ?: null,
                'schedule_type'=>$schedule,
                'frequency'=>$schedule === 'recurring' ? $frequency : null,
                'interval_count'=>1,
                'starts_after_days'=>0,
                'due_after_days'=>7,
                'occurrence_count'=>null,
                'end_after_months'=>null,
                'estimated_hours'=>0,
                'sort_order'=>$position + 1,
                'active'=>1,
                'updated_at'=>$now,
            ];
            if ($workItemId) {
                if (! $this->db->scalar('SELECT id FROM service_work_items WHERE id=? AND service_id=?', [$workItemId,$serviceId])) { throw new InvalidArgumentException('A service item could not be found.'); }
                $this->db->update('service_work_items', $workItemId, $values);
            } else {
                $values['created_at'] = $now;
                $workItemId = $this->db->insert('service_work_items', $values);
            }
            $keptIds[] = $workItemId;

        }

        foreach ($existingIds as $existingId) {
            if (! in_array($existingId, $keptIds, true)) { $this->db->execute('DELETE FROM service_work_items WHERE id=? AND service_id=?', [$existingId,$serviceId]); }
        }
    }

    private function syncPackageWorkItems(int $packageId, int $serviceId, array $submittedItems): void
    {
        $templates = $this->db->all('SELECT * FROM service_work_items WHERE service_id=? AND active=1 ORDER BY sort_order,id', [$serviceId]);
        $templatesById = [];
        foreach ($templates as $template) { $templatesById[(int)$template['id']] = $template; }
        $items = $submittedItems ?: array_map(static fn(array $template): array => [
            'service_work_item_id'=>$template['id'], 'included'=>0,
        ], $templates);
        $seen = [];
        $now = date('c');
        foreach ($items as $position => $item) {
            if (! is_array($item)) { continue; }
            $workItemId = (int)($item['service_work_item_id'] ?? $item['id'] ?? 0);
            $template = $templatesById[$workItemId] ?? null;
            if (! $template) { continue; }
            $schedule = in_array(($item['schedule_type'] ?? ''), ['one_time','recurring'], true) ? (string)$item['schedule_type'] : (string)$template['schedule_type'];
            $frequency = in_array(($item['frequency'] ?? ''), ['daily','weekly','monthly','yearly'], true) ? (string)$item['frequency'] : $template['frequency'];
            $values = [
                'package_id'=>$packageId,
                'service_work_item_id'=>$workItemId,
                'included'=>! empty($item['included']) ? 1 : 0,
                'name_override'=>$this->differentText($item['name'] ?? null, $template['name']),
                'name_ar_override'=>$this->differentText($item['name_ar'] ?? null, $template['name_ar']),
                'name_he_override'=>$this->differentText($item['name_he'] ?? null, $template['name_he']),
                'description_override'=>$this->differentText($item['task_description'] ?? $item['description'] ?? null, $template['task_description']),
                'description_ar_override'=>$this->differentText($item['task_description_ar'] ?? $item['description_ar'] ?? null, $template['task_description_ar']),
                'description_he_override'=>$this->differentText($item['task_description_he'] ?? $item['description_he'] ?? null, $template['task_description_he']),
                'value_label'=>trim((string)($item['value_label'] ?? '')) ?: null,
                'value_label_ar'=>trim((string)($item['value_label_ar'] ?? '')) ?: null,
                'value_label_he'=>trim((string)($item['value_label_he'] ?? '')) ?: null,
                'schedule_type'=>$schedule !== $template['schedule_type'] ? $schedule : null,
                'frequency'=>$schedule === 'recurring' && $frequency !== $template['frequency'] ? $frequency : null,
                'interval_count'=>null,
                'starts_after_days'=>null,
                'due_after_days'=>null,
                'occurrence_count'=>null,
                'end_after_months'=>null,
                'estimated_hours'=>null,
                'sort_order'=>$position + 1,
                'updated_at'=>$now,
            ];
            $ruleId = (int)($this->db->scalar('SELECT id FROM package_service_item_rules WHERE package_id=? AND service_work_item_id=?', [$packageId,$workItemId]) ?: 0);
            if ($ruleId) { $this->db->update('package_service_item_rules', $ruleId, $values); }
            else { $values['created_at']=$now; $this->db->insert('package_service_item_rules', $values); }
            $seen[] = $workItemId;
        }
        if ($seen) {
            $placeholders = implode(',', array_fill(0, count($seen), '?'));
            $this->db->execute('DELETE FROM package_service_item_rules WHERE package_id=? AND service_work_item_id IN (SELECT id FROM service_work_items WHERE service_id=?) AND service_work_item_id NOT IN ('.$placeholders.')', [$packageId,$serviceId,...$seen]);
        }
    }

    private function normalizeQuoteWorkItems(array $submittedItems, int $serviceId, int $packageId): array
    {
        $resolved = $this->resolvedWorkItems($serviceId, $packageId);
        $resolvedById = [];
        foreach ($resolved as $item) { $resolvedById[(int)$item['id']] = $item; }
        $items = $submittedItems ?: $resolved;
        $normalized = [];
        foreach ($items as $position => $item) {
            if (! is_array($item)) { continue; }
            $workItemId = (int)($item['service_work_item_id'] ?? $item['id'] ?? 0);
            $template = $resolvedById[$workItemId] ?? null;
            if (! $template) { continue; }
            $schedule = in_array(($item['schedule_type'] ?? ''), ['one_time','recurring'], true) ? (string)$item['schedule_type'] : (string)$template['schedule_type'];
            $frequency = in_array(($item['frequency'] ?? ''), ['daily','weekly','monthly','yearly'], true) ? (string)$item['frequency'] : $template['frequency'];
            $normalized[] = [
                'service_work_item_id'=>$workItemId,
                'included'=>array_key_exists('included', $item) ? (! empty($item['included']) ? 1 : 0) : (int)$template['included'],
                'title'=>trim((string)($item['title'] ?? $item['name'] ?? $template['name'])) ?: $template['name'],
                'title_ar'=>trim((string)($item['title_ar'] ?? $item['name_ar'] ?? $template['name_ar'])) ?: null,
                'title_he'=>trim((string)($item['title_he'] ?? $item['name_he'] ?? $template['name_he'])) ?: null,
                'description'=>trim((string)($item['description'] ?? $item['task_description'] ?? $template['task_description'])) ?: null,
                'description_ar'=>trim((string)($item['description_ar'] ?? $item['task_description_ar'] ?? $template['task_description_ar'])) ?: null,
                'description_he'=>trim((string)($item['description_he'] ?? $item['task_description_he'] ?? $template['task_description_he'])) ?: null,
                'scope_value'=>trim((string)($item['scope_value'] ?? $item['value_label'] ?? $template['value_label'])) ?: null,
                'scope_value_ar'=>trim((string)($item['scope_value_ar'] ?? $item['value_label_ar'] ?? $template['value_label_ar'])) ?: null,
                'scope_value_he'=>trim((string)($item['scope_value_he'] ?? $item['value_label_he'] ?? $template['value_label_he'])) ?: null,
                'schedule_type'=>$schedule,
                'frequency'=>$schedule === 'recurring' ? ($frequency ?: 'weekly') : null,
                'interval_count'=>1,
                'starts_after_days'=>0,
                'due_after_days'=>7,
                'occurrence_count'=>null,
                'end_after_months'=>null,
                'estimated_hours'=>0,
                'sort_order'=>$position + 1,
            ];
        }
        return $normalized;
    }

    private function differentText(mixed $value, mixed $default): ?string
    {
        $text = trim((string)$value);
        return $text !== '' && $text !== trim((string)$default) ? $text : null;
    }

    private function createDeliveryProjects(array $quote, int $clientId): void
    {
        $projectStart = date('Y-m-d');
        foreach ($quote['items'] as $proposalItem) {
            $itemType = (string)($proposalItem['item_type'] ?? 'service');
            if (! in_array($itemType, ['service','support','membership','support_contract'], true)) { continue; }
            $existingProject = (int)($this->db->scalar('SELECT id FROM projects WHERE source_proposal_item_id=?', [(int)$proposalItem['id']]) ?: 0);
            if ($existingProject) { continue; }

            $workItems = $proposalItem['work_items'] ?? [];
            if (! $workItems) {
                $catalogHours = ! empty($proposalItem['service_id'])
                    ? (float)($this->db->scalar('SELECT estimated_hours FROM services WHERE id=?', [(int)$proposalItem['service_id']]) ?: 0)
                    : 0.0;
                $taskTitle = match ($itemType) {
                    'support' => 'Activate '.$proposalItem['title'],
                    'membership' => 'Start '.$proposalItem['title'],
                    'support_contract' => 'Deliver '.$proposalItem['title'].' support cycle',
                    default => 'Deliver '.$proposalItem['title'],
                };
                $workItems = [[
                    'id'=>null,'title'=>$taskTitle,'description'=>$proposalItem['description'],'scope_value'=>null,
                    'schedule_type'=>'one_time','frequency'=>null,'interval_count'=>1,'starts_after_days'=>0,'due_after_days'=>14,
                    'occurrence_count'=>null,'end_after_months'=>null,'estimated_hours'=>$catalogHours,'sort_order'=>1,
                ]];
            }

            $estimatedHours = array_sum(array_map(static fn(array $item): float => max(0,(float)($item['estimated_hours'] ?? 0)), $workItems));
            $projectId = $this->db->insert('projects', [
                'client_id'=>$clientId,
                'package_id'=>$itemType==='support_contract' ? ($quote['support_package_id']?:null) : ($quote['package_id']?:null),
                'source_proposal_id'=>(int)$quote['id'],
                'source_proposal_item_id'=>(int)$proposalItem['id'],
                'manager_id'=>null,
                'name'=>$proposalItem['title'].' — '.$quote['business_name'],
                'project_type'=>$proposalItem['category'] ?: $proposalItem['title'],
                'start_date'=>$projectStart,
                'deadline'=>$this->projectDeadline($projectStart, $workItems),
                'budget'=>max(0,(float)$proposalItem['total']),
                'estimated_hours'=>$estimatedHours,
                'actual_hours'=>0,
                'status'=>'planning',
                'priority'=>'medium',
                'notes'=>'Created automatically from accepted quote '.$quote['proposal_number'].'.',
                'created_at'=>date('c'),
            ]);

            foreach ($workItems as $workItem) {
                $occurrence = (new DateTimeImmutable($projectStart))->modify('+'.max(0,(int)($workItem['starts_after_days'] ?? 0)).' days');
                $description = trim((string)($workItem['description'] ?? ''));
                $scope = trim((string)($workItem['scope_value'] ?? ''));
                if ($scope !== '') { $description .= ($description !== '' ? "\n\n" : '').'Package scope: '.$scope; }
                if (($workItem['schedule_type'] ?? 'one_time') === 'recurring' && ! empty($workItem['frequency'])) {
                    $description .= ($description !== '' ? "\n\n" : '').'Work pattern: '.ucfirst((string)$workItem['frequency']).' (recurrence automation is not enabled).';
                }
                $this->db->insert('project_tasks', [
                    'project_id'=>$projectId,
                    'client_id'=>$clientId,
                    'assigned_employee_id'=>null,
                    'recurrence_id'=>null,
                    'proposal_work_item_id'=>! empty($workItem['id']) ? (int)$workItem['id'] : null,
                    'title'=>$workItem['title'],
                    'description'=>$description ?: null,
                    'due_date'=>$occurrence->modify('+'.max(0,(int)($workItem['due_after_days'] ?? 0)).' days')->format('Y-m-d'),
                    'occurrence_date'=>$occurrence->format('Y-m-d'),
                    'priority'=>'medium',
                    'status'=>'todo',
                    'estimated_hours'=>max(0,(float)($workItem['estimated_hours'] ?? 0)),
                    'actual_hours'=>0,
                    'created_at'=>date('c'),
                ]);
            }

            $this->db->insert('activities', ['client_id'=>$clientId,'user_id'=>(int)$this->auth->user()['id'],'type'=>'project.created','description'=>'Project created from accepted quote: '.$proposalItem['title'],'entity_type'=>'project','entity_id'=>$projectId,'created_at'=>date('c')]);
        }
    }

    private function projectDeadline(string $startDate, array $workItems): ?string
    {
        $latest = null;
        foreach ($workItems as $workItem) {
            $start = (new DateTimeImmutable($startDate))->modify('+'.max(0,(int)($workItem['starts_after_days'] ?? 0)).' days');
            $deadline = $start->modify('+'.max(0,(int)($workItem['due_after_days'] ?? 0)).' days');
            if (! $latest || $deadline > $latest) { $latest = $deadline; }
        }
        return $latest?->format('Y-m-d');
    }

    private function relationshipIds(array $input, ?array $existing, string $businessName, string $contactName, string $email, float $value, int $score): array
    {
        $relationship = trim((string)($input['existing_relationship'] ?? ''));
        $clientId = null;
        $leadId = max(0,(int)($existing['lead_id'] ?? 0)) ?: null;
        if (preg_match('/^(client|lead):(\d+)$/', $relationship, $matches)) {
            if ($matches[1] === 'client') {
                $clientId = (int)$matches[2];
                $leadId = null;
            } else {
                $leadId = (int)$matches[2];
                $clientId = null;
            }
        } else {
            $clientId = max(0,(int)($input['client_id'] ?? $existing['client_id'] ?? 0)) ?: null;
        }
        $opportunityId = max(0,(int)($existing['opportunity_id'] ?? 0)) ?: null;
        if ($clientId) { return [$leadId,$clientId,$opportunityId]; }
        $matchingClientId = (int)($this->db->scalar("SELECT c.id FROM clients c LEFT JOIN users pu ON pu.id=c.portal_user_id WHERE c.status='active' AND (LOWER(COALESCE(c.email,''))=? OR LOWER(COALESCE(pu.email,''))=?) ORDER BY c.id LIMIT 1", [$email,$email]) ?: 0);
        if ($matchingClientId) {
            return [null,$matchingClientId,$opportunityId];
        }
        if (! $leadId) {
            $leadId = (int)($this->db->scalar('SELECT id FROM leads WHERE email=? AND converted_client_id IS NULL ORDER BY id DESC LIMIT 1', [$email]) ?: 0);
        }
        $parts = preg_split('/\s+/', $contactName, 2) ?: [$contactName];
        if (! $leadId) {
            $leadId = $this->db->insert('leads',['first_name'=>$parts[0]?:'Contact','last_name'=>$parts[1]??'','company_name'=>$businessName,'phone'=>trim((string)($input['contact_phone']??''))?:null,'email'=>$email,'website'=>trim((string)($input['website']??''))?:null,'industry'=>trim((string)($input['industry']??''))?:null,'status'=>'proposal','lead_score'=>min(100,$score*5),'estimated_budget'=>$value,'services_interested'=>'Quote Studio selection','notes'=>trim((string)($input['internal_notes']??''))?:null,'created_at'=>date('c'),'updated_at'=>date('c')]);
        } else {
            $this->db->execute("UPDATE leads SET company_name=?,first_name=?,last_name=?,phone=?,website=?,status='proposal',lead_score=?,estimated_budget=?,updated_at=? WHERE id=?",[$businessName,$parts[0]?:'Contact',$parts[1]??'',trim((string)($input['contact_phone']??''))?:null,trim((string)($input['website']??''))?:null,min(100,$score*5),$value,date('c'),$leadId]);
        }
        if (! $opportunityId) {
            $stageId=(int)$this->db->scalar("SELECT id FROM pipeline_stages WHERE slug='proposal' LIMIT 1");
            $ownerId=$this->db->scalar('SELECT id FROM employees WHERE user_id=? LIMIT 1',[(int)$this->auth->user()['id']]);
            $opportunityId=$this->db->insert('opportunities',['lead_id'=>$leadId,'client_id'=>null,'stage_id'=>$stageId,'owner_id'=>$ownerId?:null,'title'=>$businessName.' engagement','estimated_value'=>$value,'services'=>'Quote Studio selection','probability'=>75,'expected_close_date'=>date('Y-m-d',strtotime('+14 days')),'next_action'=>'Review quote with client','notes'=>'Created through Quote Studio.','stage_entered_at'=>date('c'),'created_at'=>date('c'),'updated_at'=>date('c')]);
        } else {
            $this->db->execute('UPDATE opportunities SET estimated_value=?,updated_at=? WHERE id=?',[$value,date('c'),$opportunityId]);
        }
        return [$leadId,null,$opportunityId];
    }

    private function relationshipOptions(): array
    {
        $clients = $this->db->all("SELECT c.id,'client' AS type,c.name AS contact_name,c.email,c.phone,b.name AS business_name,b.website,b.years_in_business,'existing_business' AS business_stage FROM clients c JOIN businesses b ON b.id=c.business_id WHERE c.status='active'");
        foreach ($clients as &$client) {
            $client['key'] = 'client:'.(int)$client['id'];
            $client['record_label'] = 'Client';
        }
        unset($client);

        $prospects = $this->db->all("SELECT l.id,'lead' AS type,TRIM(CONCAT(l.first_name,' ',l.last_name)) AS contact_name,l.email,l.phone,l.company_name AS business_name,l.website,l.business_stage,CASE l.years_in_business_range WHEN 'under_1' THEN 0 WHEN '1_2' THEN 2 WHEN '3_5' THEN 4 WHEN '6_10' THEN 8 WHEN '10_plus' THEN 12 ELSE 0 END AS years_in_business FROM leads l WHERE l.converted_client_id IS NULL AND l.status<>'lost'");
        foreach ($prospects as &$prospect) {
            $prospect['key'] = 'lead:'.(int)$prospect['id'];
            $prospect['record_label'] = 'Prospect';
        }
        unset($prospect);

        $relationships = array_merge($clients, $prospects);
        usort($relationships, static fn(array $left, array $right): int => strcasecmp((string)$left['business_name'], (string)$right['business_name']));

        return $relationships;
    }

    private function assessmentScore(array $answers): int
    {
        $score=0;
        foreach ((array)config('quote_studio.assessment') as $question) {
            $answer=(string)($answers[$question['key']]??'');
            foreach($question['options'] as $option){if($answer===(string)$option['value']){$score+=(int)$option['score'];break;}}
        }
        return min(20,max(0,$score));
    }

    private function decodeJson(mixed $value, string $label): array
    {
        if (is_array($value)) { return $value; }
        $decoded=json_decode((string)$value,true);
        if(!is_array($decoded)){throw new InvalidArgumentException('The '.$label.' data is invalid.');}
        return $decoded;
    }

    private function nextNumber(): string
    {
        $prefix='Q-'.date('Ymd').'-';
        $last=(string)($this->db->scalar('SELECT proposal_number FROM proposals WHERE proposal_number LIKE ? ORDER BY id DESC LIMIT 1',[$prefix.'%'])??'');
        $sequence=(int)substr($last,-4)+1;
        return $prefix.str_pad((string)$sequence,4,'0',STR_PAD_LEFT);
    }

    private function audit(string $action,string $details,string $entityType,int $entityId): void
    {
        $this->db->insert('audit_logs',['user_id'=>(int)$this->auth->user()['id'],'action'=>$action,'entity_type'=>$entityType,'entity_id'=>$entityId,'old_values'=>null,'new_values'=>json_encode(['summary'=>$details],JSON_UNESCAPED_UNICODE),'ip_address'=>request()->ip(),'created_at'=>date('c')]);
    }
}
