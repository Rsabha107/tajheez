<?php

namespace Database\Seeders;

use App\Models\Ems\Event;
use App\Models\Ems\Venue;
use App\Models\MaterialPlanning\Area;
use App\Models\MaterialPlanning\CatalogItem;
use App\Models\MaterialPlanning\ChangeOrder;
use App\Models\MaterialPlanning\Domain;
use App\Models\MaterialPlanning\ItemGroup;
use App\Models\MaterialPlanning\ItemSubgroup;
use App\Models\MaterialPlanning\MaterialRequest;
use App\Models\MaterialPlanning\ServiceOption;
use App\Models\MaterialPlanning\ServiceOptionItem;
use App\Models\MaterialPlanning\Space;
use App\Models\MaterialPlanning\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialPlanningSeeder extends Seeder
{
    /** Real event this demo data is attached to (FIFA Intercontinental Cup 2025). */
    private const EVENT_ID = 10;

    /** Fictional mock venue code => real venue short_name, both real stadiums in the live venues table. */
    private const VENUE_MAP = [
        'USA-MET' => 'KIS', // Khalifa International Stadium
        'USA-SOF' => 'LUS', // Lusail Stadium
        'MEX-AZT' => 'AZC', // Aspire Zone Complex
        'CAN-BMO' => 'AAS', // Ahmad bin Ali Stadium
        'USA-ATT' => 'ABS', // Al Bayt Stadium
        'USA-NRG' => 'ECS', // Education City Stadium
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $venueIds = Venue::whereIn('short_name', array_values(self::VENUE_MAP))->pluck('id', 'short_name');
            $event = Event::findOrFail(self::EVENT_ID);
            if (! $event->start_date) {
                $event->update(['start_date' => now()->addDays(30), 'end_date' => now()->addDays(68)]);
            }

            $people = $this->seedPeople();
            $this->seedDomains();
            $this->seedAreasAndSpaces();
            $this->seedItemGroupsAndSubgroups();
            $catalog = $this->seedCatalog();
            $this->seedSuppliers();
            $this->seedServiceOptions();
            $requests = $this->seedRequests($catalog, $venueIds, $people, $event->id);
            $this->seedChangeOrders($requests, $catalog, $people);
        });
    }

    /**
     * Named demo users matching the old mock "people" directory, so avatar
     * initials line up with what's still hardcoded in a couple of Vue views
     * (the routing-preview chain in NewRequestView/DetailView). Reuses the
     * domain-manager@example.com account created earlier this session for
     * "Jordan Kim" rather than duplicating it.
     *
     * @return array<string, User> keyed by mock initials
     */
    private function seedPeople(): array
    {
        $jordan = User::firstWhere('email', 'domain-manager@example.com')
            ?? User::factory()->create(['name' => 'Jordan Kim', 'email' => 'domain-manager@example.com', 'managed_domain' => 'IT']);
        if (! $jordan->hasRole('domain-manager')) {
            $jordan->assignRole('domain-manager');
        }

        $make = function (string $name, string $email, ?string $domain = null) {
            $user = User::firstWhere('email', $email) ?? User::factory()->create([
                'name' => $name, 'email' => $email, 'managed_domain' => $domain,
            ]);
            if ($domain && ! $user->hasRole('domain-manager')) {
                $user->assignRole('domain-manager');
            }
            return $user;
        };

        return [
            'JK' => $jordan,
            'MC' => $make('Marta Conti', 'marta.conti@example.com', 'OVR'),
            'TN' => $make('Tariq Nasir', 'tariq.nasir@example.com', 'PWR'),
            'LH' => $make('Lukas Hofer', 'lukas.hofer@example.com', 'LOG'),
            'SO' => $make('Sara Othman', 'sara.othman@example.com'),
            'AR' => $make('Amal Rashid', 'amal.rashid@example.com'),
            'DV' => $make('Diego Vargas', 'diego.vargas@example.com'),
        ];
    }

    private function seedDomains(): void
    {
        $rows = [
            ['code' => 'IT',  'label' => 'IT',        'color' => '#1d4ed8', 'chip' => '#dbeafe', 'description' => 'Network, AV, devices',            'sort_order' => 1],
            ['code' => 'LOG', 'label' => 'Logistics', 'color' => '#7c2d12', 'chip' => '#fde7d3', 'description' => 'Furniture, tents, signage',        'sort_order' => 2],
            ['code' => 'PWR', 'label' => 'Power',     'color' => '#b45309', 'chip' => '#fef3c7', 'description' => 'Generators, distro, cabling',      'sort_order' => 3],
            ['code' => 'OVR', 'label' => 'Overlay',   'color' => '#0f766e', 'chip' => '#ccfbf1', 'description' => 'Fencing, flooring, structures',    'sort_order' => 4],
            ['code' => 'FFE', 'label' => 'FF&E',      'color' => '#6b21a8', 'chip' => '#ede9fe', 'description' => 'Furniture, fittings & equipment', 'sort_order' => 5],
        ];
        foreach ($rows as $row) {
            Domain::updateOrCreate(['code' => $row['code']], $row);
        }
    }

    private function seedAreasAndSpaces(): void
    {
        $areas = [
            ['code' => 'SPORT',      'label' => 'Sport',       'description' => 'Field of play, athlete and technical areas', 'sort_order' => 1],
            ['code' => 'OPS',        'label' => 'Operations',  'description' => 'Venue operations, security, logistics',       'sort_order' => 2],
            ['code' => 'MEDIA',      'label' => 'Media',       'description' => 'Broadcast, press and photography',            'sort_order' => 3],
            ['code' => 'COMM',       'label' => 'Commercial',  'description' => 'Hospitality, sponsors, ticketing',            'sort_order' => 4],
            ['code' => 'WORKFORCE',  'label' => 'Workforce',   'description' => 'Volunteers, staff and accreditation',         'sort_order' => 5],
        ];
        foreach ($areas as $row) {
            Area::updateOrCreate(['code' => $row['code']], $row);
        }
        $areasByCode = Area::pluck('id', 'code');

        $spaces = [
            ['code' => '3.3.0', 'area_code' => 'MEDIA',     'name' => 'Mixed Zone',            'description' => 'Post-match athlete/media interaction zone'],
            ['code' => '3.1.0', 'area_code' => 'MEDIA',     'name' => 'Press Tribune',         'description' => 'Seated written-press working area'],
            ['code' => '3.2.0', 'area_code' => 'MEDIA',     'name' => 'Broadcast Compound',    'description' => 'Rights-holder broadcast production area'],
            ['code' => '3.5.0', 'area_code' => 'MEDIA',     'name' => 'Media Centre',          'description' => 'Main working media centre'],
            ['code' => '2.1.0', 'area_code' => 'OPS',       'name' => 'Operations Centre',     'description' => 'Venue command and control'],
            ['code' => '2.2.0', 'area_code' => 'OPS',       'name' => 'Medical Clinic',        'description' => 'Spectator and workforce medical care'],
            ['code' => '1.1.0', 'area_code' => 'SPORT',     'name' => 'Anti-Doping',           'description' => 'Doping control station'],
            ['code' => '4.1.0', 'area_code' => 'COMM',      'name' => 'VVIP Lounge',           'description' => 'VVIP hospitality suite'],
            ['code' => '4.2.0', 'area_code' => 'COMM',      'name' => 'Catering Tent',         'description' => 'Back-of-house catering and hospitality'],
            ['code' => '5.1.0', 'area_code' => 'WORKFORCE', 'name' => 'Volunteer Hub',         'description' => 'Volunteer check-in and rest area'],
            ['code' => '5.2.0', 'area_code' => 'WORKFORCE', 'name' => 'Accreditation Centre',  'description' => 'Accreditation issuance and support'],
        ];
        foreach ($spaces as $row) {
            $areaCode = $row['area_code'];
            unset($row['area_code']);
            $row['area_id'] = $areasByCode[$areaCode];
            Space::updateOrCreate(['code' => $row['code']], $row);
        }
    }

    /**
     * Mirrors the group/sub strings already used on catalog items (see
     * seedCatalog() below), so the Item Groups/Subgroups admin screens have
     * data consistent with what's actually on the catalog.
     */
    private function seedItemGroupsAndSubgroups(): void
    {
        $domainsByCode = Domain::pluck('id', 'code');

        $groups = [
            ['code' => 'IT-NETWORK',     'domain' => 'IT',  'label' => 'Network',    'sort_order' => 1],
            ['code' => 'IT-AV',          'domain' => 'IT',  'label' => 'AV',         'sort_order' => 2],
            ['code' => 'IT-DEVICES',     'domain' => 'IT',  'label' => 'Devices',    'sort_order' => 3],
            ['code' => 'LOG-TENTS',      'domain' => 'LOG', 'label' => 'Tents',      'sort_order' => 1],
            ['code' => 'LOG-FURNITURE',  'domain' => 'LOG', 'label' => 'Furniture',  'sort_order' => 2],
            ['code' => 'LOG-SIGNAGE',    'domain' => 'LOG', 'label' => 'Signage',    'sort_order' => 3],
            ['code' => 'PWR-GENERATORS', 'domain' => 'PWR', 'label' => 'Generators', 'sort_order' => 1],
            ['code' => 'PWR-DISTRO',     'domain' => 'PWR', 'label' => 'Distro',     'sort_order' => 2],
            ['code' => 'PWR-CABLING',    'domain' => 'PWR', 'label' => 'Cabling',    'sort_order' => 3],
            ['code' => 'OVR-FLOORING',   'domain' => 'OVR', 'label' => 'Flooring',   'sort_order' => 1],
            ['code' => 'OVR-FENCING',    'domain' => 'OVR', 'label' => 'Fencing',    'sort_order' => 2],
            ['code' => 'OVR-STRUCTURES', 'domain' => 'OVR', 'label' => 'Structures', 'sort_order' => 3],
            ['code' => 'FFE-FURNITURE',  'domain' => 'FFE', 'label' => 'Furniture',  'sort_order' => 1],
            ['code' => 'FFE-FITTINGS',   'domain' => 'FFE', 'label' => 'Fittings',   'sort_order' => 2],
        ];

        $groupIds = [];
        foreach ($groups as $row) {
            $domainCode = $row['domain'];
            unset($row['domain']);
            $row['domain_id'] = $domainsByCode[$domainCode];
            $group = ItemGroup::updateOrCreate(['code' => $row['code']], $row);
            $groupIds[$group->code] = $group->id;
        }

        $subgroups = [
            ['code' => 'IT-NETWORK-SW',     'group' => 'IT-NETWORK',      'name' => 'Switches'],
            ['code' => 'IT-NETWORK-AP',     'group' => 'IT-NETWORK',      'name' => 'Access Points'],
            ['code' => 'IT-NETWORK-CONN',   'group' => 'IT-NETWORK',      'name' => 'Connectivity'],
            ['code' => 'IT-AV-DISPLAYS',    'group' => 'IT-AV',           'name' => 'Displays'],
            ['code' => 'IT-AV-MICS',        'group' => 'IT-AV',           'name' => 'Microphones'],
            ['code' => 'IT-DEV-LAPTOPS',    'group' => 'IT-DEVICES',      'name' => 'Laptops'],
            ['code' => 'IT-DEV-TABLETS',    'group' => 'IT-DEVICES',      'name' => 'Tablets'],
            ['code' => 'LOG-TENTS-STRUCT',  'group' => 'LOG-TENTS',       'name' => 'Structures'],
            ['code' => 'LOG-FURN-SEATING',  'group' => 'LOG-FURNITURE',   'name' => 'Seating'],
            ['code' => 'LOG-FURN-TABLES',   'group' => 'LOG-FURNITURE',   'name' => 'Tables'],
            ['code' => 'LOG-SIGN-WAY',      'group' => 'LOG-SIGNAGE',     'name' => 'Wayfinding'],
            ['code' => 'PWR-GEN-DIESEL',    'group' => 'PWR-GENERATORS',  'name' => 'Diesel'],
            ['code' => 'PWR-DIST-PANELS',   'group' => 'PWR-DISTRO',      'name' => 'Panels'],
            ['code' => 'PWR-CAB-POWER',     'group' => 'PWR-CABLING',     'name' => 'Power Cable'],
            ['code' => 'OVR-FLOOR-MODULAR', 'group' => 'OVR-FLOORING',    'name' => 'Modular'],
            ['code' => 'OVR-FENCE-HERAS',   'group' => 'OVR-FENCING',     'name' => 'Heras'],
            ['code' => 'OVR-STRUCT-BLCHR',  'group' => 'OVR-STRUCTURES',  'name' => 'Bleachers'],
            ['code' => 'FFE-FURN-LOUNGE',   'group' => 'FFE-FURNITURE',   'name' => 'Lounge'],
            ['code' => 'FFE-FIT-LIGHTING',  'group' => 'FFE-FITTINGS',    'name' => 'Lighting'],
        ];

        foreach ($subgroups as $row) {
            $groupCode = $row['group'];
            unset($row['group']);
            $row['group_id'] = $groupIds[$groupCode];
            ItemSubgroup::updateOrCreate(['code' => $row['code']], $row);
        }
    }

    /** @return array<string, CatalogItem> keyed by sku, in original mock order (matters for line generation below) */
    private function seedCatalog(): array
    {
        $rows = [
            ['sku' => 'IT-NW-0214', 'domain_code' => 'IT',  'group' => 'Network',    'sub' => 'Switches',      'name' => 'Cisco Catalyst 9300-48P',          'unit' => 'ea',       'rate' => 6240,  'stock' => 184, 'baseline' => 240],
            ['sku' => 'IT-NW-0301', 'domain_code' => 'IT',  'group' => 'Network',    'sub' => 'Access Points', 'name' => 'Aruba AP-635 Wi-Fi 6E',            'unit' => 'ea',       'rate' => 820,   'stock' => 512, 'baseline' => 640],
            ['sku' => 'IT-AV-0102', 'domain_code' => 'IT',  'group' => 'AV',         'sub' => 'Displays',      'name' => 'Samsung 75" QM75B Commercial',      'unit' => 'ea',       'rate' => 2950,  'stock' => 96,  'baseline' => 120],
            ['sku' => 'IT-AV-0411', 'domain_code' => 'IT',  'group' => 'AV',         'sub' => 'Microphones',   'name' => 'Shure ULXD24/SM58 Wireless',        'unit' => 'ea',       'rate' => 1840,  'stock' => 72,  'baseline' => 80],
            ['sku' => 'IT-DV-0220', 'domain_code' => 'IT',  'group' => 'Devices',    'sub' => 'Laptops',       'name' => 'Dell Latitude 5450 i7/16/512',      'unit' => 'ea',       'rate' => 1690,  'stock' => 640, 'baseline' => 720],
            ['sku' => 'IT-DV-0044', 'domain_code' => 'IT',  'group' => 'Devices',    'sub' => 'Tablets',       'name' => 'iPad 10.9" Wi-Fi 64GB',             'unit' => 'ea',       'rate' => 430,   'stock' => 380, 'baseline' => 420],
            ['sku' => 'IT-NW-0450', 'domain_code' => 'IT',  'group' => 'Network',    'sub' => 'Connectivity',  'name' => 'Managed Event Wi-Fi — per site',    'unit' => 'site',     'rate' => 24800, 'stock' => 38,  'baseline' => 48],
            ['sku' => 'IT-NW-0460', 'domain_code' => 'IT',  'group' => 'Network',    'sub' => 'Connectivity',  'name' => 'Dedicated Internet Circuit 1 Gbps', 'unit' => 'circuit',  'rate' => 17400, 'stock' => 22,  'baseline' => 30],
            ['sku' => 'LG-TN-0610', 'domain_code' => 'LOG', 'group' => 'Tents',      'sub' => 'Structures',    'name' => 'Standard Tent 6×6 m w/ Floor',      'unit' => 'ea',       'rate' => 2400,  'stock' => 144, 'baseline' => 180],
            ['sku' => 'LG-TN-1010', 'domain_code' => 'LOG', 'group' => 'Tents',      'sub' => 'Structures',    'name' => 'Standard Tent 10×10 m w/ Floor',    'unit' => 'ea',       'rate' => 5180,  'stock' => 96,  'baseline' => 120],
            ['sku' => 'LG-FN-0312', 'domain_code' => 'LOG', 'group' => 'Furniture',  'sub' => 'Seating',       'name' => 'Stacking Chair – Black Vinyl',       'unit' => 'ea',       'rate' => 28,    'stock' => 4800, 'baseline' => 5200],
            ['sku' => 'LG-FN-0420', 'domain_code' => 'LOG', 'group' => 'Furniture',  'sub' => 'Tables',        'name' => '6 ft Banquet Table',                'unit' => 'ea',       'rate' => 62,    'stock' => 1200, 'baseline' => 1400],
            ['sku' => 'LG-SG-0188', 'domain_code' => 'LOG', 'group' => 'Signage',    'sub' => 'Wayfinding',    'name' => 'A1 Wayfinding Stand, Double-sided',  'unit' => 'ea',       'rate' => 88,    'stock' => 380, 'baseline' => 520],
            ['sku' => 'PW-GN-0500', 'domain_code' => 'PWR', 'group' => 'Generators', 'sub' => 'Diesel',        'name' => 'Cummins C500D6 500 kVA Generator',   'unit' => 'ea',       'rate' => 18400, 'stock' => 24,  'baseline' => 32],
            ['sku' => 'PW-GN-0125', 'domain_code' => 'PWR', 'group' => 'Generators', 'sub' => 'Diesel',        'name' => 'Cummins C125D5 125 kVA Generator',   'unit' => 'ea',       'rate' => 6800,  'stock' => 48,  'baseline' => 60],
            ['sku' => 'PW-DT-0063', 'domain_code' => 'PWR', 'group' => 'Distro',     'sub' => 'Panels',        'name' => '63 A 3-phase Distro Panel',          'unit' => 'ea',       'rate' => 740,   'stock' => 120, 'baseline' => 160],
            ['sku' => 'PW-CB-1632', 'domain_code' => 'PWR', 'group' => 'Cabling',    'sub' => 'Power Cable',   'name' => '32 A Cable, 25 m',                  'unit' => 'ea',       'rate' => 140,   'stock' => 640, 'baseline' => 800],
            ['sku' => 'OV-FL-0420', 'domain_code' => 'OVR', 'group' => 'Flooring',   'sub' => 'Modular',       'name' => 'Modular Event Flooring 0.5×0.5 m',   'unit' => 'm²',       'rate' => 42,    'stock' => 3200, 'baseline' => 4000],
            ['sku' => 'OV-FN-2200', 'domain_code' => 'OVR', 'group' => 'Fencing',    'sub' => 'Heras',         'name' => 'Heras Fence Panel 3.5 m',            'unit' => 'ea',       'rate' => 36,    'stock' => 1800, 'baseline' => 2200],
            ['sku' => 'OV-ST-0102', 'domain_code' => 'OVR', 'group' => 'Structures', 'sub' => 'Bleachers',     'name' => 'Modular Bleacher – 200 seat',         'unit' => 'unit',    'rate' => 14800, 'stock' => 8,   'baseline' => 12],
            ['sku' => 'FE-FN-0101', 'domain_code' => 'FFE', 'group' => 'Furniture',  'sub' => 'Lounge',        'name' => 'Lounge Sofa – Charcoal Linen',       'unit' => 'ea',       'rate' => 480,   'stock' => 96,  'baseline' => 120],
            ['sku' => 'FE-FT-0044', 'domain_code' => 'FFE', 'group' => 'Fittings',   'sub' => 'Lighting',      'name' => 'Standing Floor Lamp, brass',         'unit' => 'ea',       'rate' => 140,   'stock' => 160, 'baseline' => 200],
        ];

        $domainsByCode = Domain::pluck('id', 'code');

        $items = [];
        foreach ($rows as $row) {
            $domainCode = $row['domain_code'];
            unset($row['domain_code']);
            $row['domain_id'] = $domainsByCode[$domainCode];
            $items[$row['sku']] = CatalogItem::updateOrCreate(['sku' => $row['sku']], $row);
        }
        return $items;
    }

    private function seedSuppliers(): void
    {
        $rows = [
            ['code' => 'SUP-VOD', 'name' => 'Vodafone Business',     'kind' => 'Telecom',             'status' => 'preferred', 'msa_reference' => 'MSA-FIC25-VOD', 'contact_name' => 'Yousef Al-Sada',    'contact_phone' => '+974 5512 3401'],
            ['code' => 'SUP-OOR', 'name' => 'Ooredoo Enterprise',    'kind' => 'Telecom',             'status' => 'preferred', 'msa_reference' => 'MSA-FIC25-OOR', 'contact_name' => 'Fatima Al-Kuwari',  'contact_phone' => '+974 5512 3402'],
            ['code' => 'SUP-DEL', 'name' => 'Dell Technologies',     'kind' => 'IT hardware',         'status' => 'active',    'msa_reference' => 'MSA-FIC25-DEL', 'contact_name' => 'Ryan Fitzgerald',   'contact_phone' => '+974 5512 3403'],
            ['code' => 'SUP-INS', 'name' => 'Insight Event Rentals', 'kind' => 'IT & FF&E rental',    'status' => 'active',    'msa_reference' => 'MSA-FIC25-INS', 'contact_name' => 'Priya Nair',        'contact_phone' => '+974 5512 3404'],
            ['code' => 'SUP-GES', 'name' => 'GES Event Services',    'kind' => 'Overlay & furniture', 'status' => 'preferred', 'msa_reference' => 'MSA-FIC25-GES', 'contact_name' => 'Marco Bianchi',     'contact_phone' => '+974 5512 3405'],
            ['code' => 'SUP-LOX', 'name' => 'Losberger De Boer',     'kind' => 'Structures',          'status' => 'active',    'msa_reference' => 'MSA-FIC25-LOX', 'contact_name' => 'Sven Hendricks',    'contact_phone' => '+974 5512 3406'],
            ['code' => 'SUP-AGG', 'name' => 'Aggreko',               'kind' => 'Power',               'status' => 'preferred', 'msa_reference' => 'MSA-FIC25-AGG', 'contact_name' => 'Callum Reid',       'contact_phone' => '+974 5512 3407'],
            ['code' => 'SUP-ALG', 'name' => 'Al Ghurair Rentals',    'kind' => 'Furniture & plant',   'status' => 'suspended', 'msa_reference' => 'MSA-FIC25-ALG', 'contact_name' => 'Hassan Al-Ghurair', 'contact_phone' => '+974 5512 3408'],
            ['code' => 'SUP-ITX', 'name' => 'Internal IT Pool',      'kind' => 'In-house',            'status' => 'active',    'msa_reference' => null,            'contact_name' => 'Jordan Kim',        'contact_phone' => '+974 5512 3409'],
            ['code' => 'SUP-OWN', 'name' => 'Tajheez Own Pool',      'kind' => 'In-house',            'status' => 'active',    'msa_reference' => null,            'contact_name' => 'Lukas Hofer',       'contact_phone' => '+974 5512 3410'],
        ];
        $classificationByStatus = ['preferred' => 1, 'active' => 2, 'suspended' => 3];
        foreach ($rows as $row) {
            $row['classification_id'] = $classificationByStatus[$row['status']];
            unset($row['status']);
            Supplier::updateOrCreate(['code' => $row['code']], $row);
        }
    }

    /**
     * Service options are now a reusable library (`ServiceOptionItem`) that
     * bundles (`ServiceOption`) pick from via a many-to-many pivot. Each seed
     * row below becomes one reusable item plus a single-item bundle of the
     * same name (preserving the "one bundle per historical option" shape a
     * request line can assign), and a handful of combo bundles below that
     * reuse two of those items each, to exercise reuse/usage-count for real.
     */
    private function seedServiceOptions(): void
    {
        $rows = [
            ['code' => 'SO-0450-VOD', 'sku' => 'IT-NW-0450', 'name' => 'Vodafone Managed Services',  'supplier_code' => 'SUP-VOD', 'cost' => 24800, 'lead_days' => 14, 'sla' => '4h on-site, 24/7',          'capacity' => 12,   'contract_reference' => 'CTR-FIC25-VOD-014', 'spec' => 'Wi-Fi 6E, 2,000 concurrent clients, dual uplink + LTE failover', 'status' => 'preferred', 'is_default' => true],
            ['code' => 'SO-0450-OOR', 'sku' => 'IT-NW-0450', 'name' => 'Ooredoo Managed Wi-Fi',      'supplier_code' => 'SUP-OOR', 'cost' => 22400, 'lead_days' => 10, 'sla' => '8h on-site',                'capacity' => 8,    'contract_reference' => 'CTR-FIC25-OOR-009', 'spec' => 'Wi-Fi 6, 1,500 concurrent clients, single uplink',              'status' => 'active'],
            ['code' => 'SO-0450-ITX', 'sku' => 'IT-NW-0450', 'name' => 'Temporary Wi-Fi — internal', 'supplier_code' => 'SUP-ITX', 'cost' => 8600,  'lead_days' => 3,  'sla' => 'Best effort, staffed hours', 'capacity' => 4,   'contract_reference' => null,                'spec' => 'Wi-Fi 6, 400 clients, no redundancy — back-of-house only',       'status' => 'active'],
            ['code' => 'SO-0460-OOR', 'sku' => 'IT-NW-0460', 'name' => 'Ooredoo Fiber 1 Gbps',       'supplier_code' => 'SUP-OOR', 'cost' => 16500, 'lead_days' => 14, 'sla' => '99.9% · 4h MTTR',           'capacity' => 6,    'contract_reference' => 'CTR-FIC25-OOR-011', 'spec' => 'Single-mode fiber, 1 Gbps symmetric, /29 public',               'status' => 'preferred', 'is_default' => true],
            ['code' => 'SO-0460-VOD', 'sku' => 'IT-NW-0460', 'name' => 'Vodafone Dedicated 1 Gbps',  'supplier_code' => 'SUP-VOD', 'cost' => 18200, 'lead_days' => 21, 'sla' => '99.95% · 2h MTTR',          'capacity' => 8,    'contract_reference' => 'CTR-FIC25-VOD-017', 'spec' => 'Diverse-path fiber, 1 Gbps symmetric, /28 public',              'status' => 'active'],
            ['code' => 'SO-0220-DEL', 'sku' => 'IT-DV-0220', 'name' => 'Dell Latitude — purchase',   'supplier_code' => 'SUP-DEL', 'cost' => 1690,  'lead_days' => 21, 'sla' => 'NBD swap, 3-yr warranty',   'capacity' => 240,  'contract_reference' => 'CTR-FIC25-DEL-002', 'spec' => 'i7 / 16 GB / 512 GB, imaged to the event gold build',           'status' => 'active', 'is_default' => true],
            ['code' => 'SO-0220-INS', 'sku' => 'IT-DV-0220', 'name' => 'Latitude — event rental',    'supplier_code' => 'SUP-INS', 'cost' => 640,   'lead_days' => 7,  'sla' => '4h swap on-site',           'capacity' => 180,  'contract_reference' => 'CTR-FIC25-INS-005', 'spec' => 'Equivalent i7 / 16 GB, returned post-event',                    'status' => 'preferred'],
            ['code' => 'SO-0220-OWN', 'sku' => 'IT-DV-0220', 'name' => 'Own pool — refurbished',     'supplier_code' => 'SUP-OWN', 'cost' => 320,   'lead_days' => 2,  'sla' => 'Spare-pool swap',           'capacity' => 90,   'contract_reference' => null,                'spec' => 'i5 / 16 GB ex-event stock, 90-day burn-in',                     'status' => 'active'],
            ['code' => 'SO-0301-OWN', 'sku' => 'IT-NW-0301', 'name' => 'Own pool deployment',        'supplier_code' => 'SUP-OWN', 'cost' => 820,   'lead_days' => 2,  'sla' => 'Spare-pool swap',           'capacity' => 120,  'contract_reference' => null,                'spec' => 'Aruba AP-635, configured by internal network team',              'status' => 'active', 'is_default' => true],
            ['code' => 'SO-0301-VOD', 'sku' => 'IT-NW-0301', 'name' => 'Managed AP — Vodafone',      'supplier_code' => 'SUP-VOD', 'cost' => 940,   'lead_days' => 14, 'sla' => '4h on-site, 24/7',          'capacity' => 200,  'contract_reference' => 'CTR-FIC25-VOD-014', 'spec' => 'Same hardware, includes install, monitoring and match-day cover', 'status' => 'preferred'],
            ['code' => 'SO-0214-OWN', 'sku' => 'IT-NW-0214', 'name' => 'Own pool deployment',        'supplier_code' => 'SUP-OWN', 'cost' => 6240,  'lead_days' => 2,  'sla' => 'Spare-pool swap',           'capacity' => 40,   'contract_reference' => null,                'spec' => 'Catalyst 9300-48P, internal config templates',                  'status' => 'active', 'is_default' => true],
            ['code' => 'SO-0214-OOR', 'sku' => 'IT-NW-0214', 'name' => 'Managed LAN — Ooredoo',      'supplier_code' => 'SUP-OOR', 'cost' => 6820,  'lead_days' => 12, 'sla' => '8h on-site',                'capacity' => 64,   'contract_reference' => 'CTR-FIC25-OOR-009', 'spec' => 'Supplier-owned switch, monitored, removed post-event',          'status' => 'active'],
            ['code' => 'SO-0102-GES', 'sku' => 'IT-AV-0102', 'name' => 'Display rental + mount',     'supplier_code' => 'SUP-GES', 'cost' => 2950,  'lead_days' => 7,  'sla' => 'NBD replacement',           'capacity' => 60,   'contract_reference' => 'CTR-FIC25-GES-021', 'spec' => 'Samsung 75" QM75B with floor stand and cable management',        'status' => 'active', 'is_default' => true],
            ['code' => 'SO-0102-OWN', 'sku' => 'IT-AV-0102', 'name' => 'Own pool — screen only',     'supplier_code' => 'SUP-OWN', 'cost' => 2480,  'lead_days' => 3,  'sla' => 'Spare-pool swap',           'capacity' => 24,   'contract_reference' => null,                'spec' => 'Screen only — mounts sourced separately',                       'status' => 'active'],
            ['code' => 'SO-0500-AGG', 'sku' => 'PW-GN-0500', 'name' => 'Managed 500 kVA + fuel',     'supplier_code' => 'SUP-AGG', 'cost' => 18400, 'lead_days' => 18, 'sla' => 'On-site technician, 24/7',  'capacity' => 8,    'contract_reference' => 'CTR-FIC25-AGG-003', 'spec' => '500 kVA Tier-4, fuel polishing, load bank tested, attended',     'status' => 'preferred', 'is_default' => true],
            ['code' => 'SO-0500-ALG', 'sku' => 'PW-GN-0500', 'name' => 'Dry hire 500 kVA',           'supplier_code' => 'SUP-ALG', 'cost' => 16900, 'lead_days' => 25, 'sla' => 'Next-day call-out',         'capacity' => 4,    'contract_reference' => 'CTR-FIC25-ALG-008', 'spec' => 'Unattended dry hire — fuel and operators by us',                'status' => 'suspended'],
            ['code' => 'SO-1010-LOX', 'sku' => 'LG-TN-1010', 'name' => 'Structure + floor install',  'supplier_code' => 'SUP-LOX', 'cost' => 5180,  'lead_days' => 30, 'sla' => 'Build crew on site',        'capacity' => 20,   'contract_reference' => 'CTR-FIC25-LOX-012', 'spec' => '10×10 m clearspan, hard floor, wind-rated to 100 km/h',         'status' => 'active', 'is_default' => true],
            ['code' => 'SO-1010-GES', 'sku' => 'LG-TN-1010', 'name' => 'Structure — expedited',      'supplier_code' => 'SUP-GES', 'cost' => 5420,  'lead_days' => 21, 'sla' => 'Build crew on site',        'capacity' => 12,   'contract_reference' => 'CTR-FIC25-GES-021', 'spec' => 'Equivalent clearspan, shorter lead, same wind rating',          'status' => 'preferred'],
            ['code' => 'SO-0420-GES', 'sku' => 'OV-FL-0420', 'name' => 'Supply & lay',               'supplier_code' => 'SUP-GES', 'cost' => 42,    'lead_days' => 12, 'sla' => 'Snag crew within 24h',      'capacity' => 1200, 'contract_reference' => 'CTR-FIC25-GES-021', 'spec' => '0.5×0.5 m modular deck, levelled and edge-trimmed',             'status' => 'active', 'is_default' => true],
            ['code' => 'SO-0420-LOX', 'sku' => 'OV-FL-0420', 'name' => 'Supply & lay — expedited',   'supplier_code' => 'SUP-LOX', 'cost' => 46,    'lead_days' => 9,  'sla' => 'Snag crew within 12h',      'capacity' => 900,  'contract_reference' => 'CTR-FIC25-LOX-012', 'spec' => 'Equivalent deck, priority crew allocation',                     'status' => 'active'],
            ['code' => 'SO-0101-GES', 'sku' => 'FE-FN-0101', 'name' => 'Lounge set rental',          'supplier_code' => 'SUP-GES', 'cost' => 480,   'lead_days' => 14, 'sla' => 'Replacement within 48h',    'capacity' => 40,   'contract_reference' => 'CTR-FIC25-GES-021', 'spec' => 'Charcoal linen, fire-certified, delivered dressed',             'status' => 'active', 'is_default' => true],
            ['code' => 'SO-0101-INS', 'sku' => 'FE-FN-0101', 'name' => 'Lounge set — value tier',    'supplier_code' => 'SUP-INS', 'cost' => 395,   'lead_days' => 10, 'sla' => 'Replacement within 72h',    'capacity' => 60,   'contract_reference' => 'CTR-FIC25-INS-005', 'spec' => 'Equivalent frame, grey weave, delivered flat-packed',           'status' => 'active'],
        ];
        $suppliersByCode = Supplier::pluck('id', 'code');
        $classificationByStatus = ['preferred' => 1, 'active' => 2, 'suspended' => 3];

        $itemsByCode = [];
        foreach ($rows as $row) {
            $itemsByCode[$row['code']] = ServiceOptionItem::updateOrCreate(['code' => $row['code']], [
                'name' => $row['name'],
                'supplier_id' => $suppliersByCode[$row['supplier_code']],
                'cost' => $row['cost'],
                'lead_days' => $row['lead_days'],
                'sla' => $row['sla'],
                'capacity' => $row['capacity'],
                'contract_reference' => $row['contract_reference'],
                'spec' => $row['spec'],
            ]);
        }

        foreach ($rows as $row) {
            $bundle = ServiceOption::updateOrCreate(['code' => $row['code']], [
                'name' => $row['name'],
                'classification_id' => $classificationByStatus[$row['status']],
                'status_id' => 1,
                'is_default' => $row['is_default'] ?? false,
            ]);

            $bundle->services()->sync([$itemsByCode[$row['code']]->id => ['sort_order' => 0]]);
        }

        // A few bundles that combine more than one reusable item, so the
        // library's reuse/usage-count feature has real data to show.
        $combos = [
            ['code' => 'SO-BND-NET01', 'name' => 'Vodafone network package (Wi-Fi + fiber)',  'status' => 'preferred', 'items' => ['SO-0450-VOD', 'SO-0460-VOD']],
            ['code' => 'SO-BND-PWR01', 'name' => 'Aggreko power + GES structure',              'status' => 'active',    'items' => ['SO-0500-AGG', 'SO-1010-GES']],
            ['code' => 'SO-BND-LNG01', 'name' => 'GES lounge + flooring package',              'status' => 'active',    'items' => ['SO-0101-GES', 'SO-0420-GES']],
        ];
        foreach ($combos as $combo) {
            $bundle = ServiceOption::updateOrCreate(['code' => $combo['code']], [
                'name' => $combo['name'],
                'classification_id' => $classificationByStatus[$combo['status']],
                'status_id' => 1,
                'is_default' => false,
            ]);

            $pivot = [];
            foreach (array_values($combo['items']) as $i => $itemCode) {
                $pivot[$itemsByCode[$itemCode]->id] = ['sort_order' => $i];
            }
            $bundle->services()->sync($pivot);
        }
    }

    /**
     * @param array<string, CatalogItem> $catalog
     * @return array<string, MaterialRequest> keyed by mock request id
     */
    private function seedRequests(array $catalog, $venueIds, array $people, int $eventId): array
    {
        $headers = [
            'MR-26-04188' => ['title' => 'Mixed Zone build-out — Media tier 1',    'venue' => 'USA-MET', 'site' => 'Mixed Zone — North',       'domain' => 'OVR', 'status' => 'l2',        'items' => 14, 'value' => 184320, 'submittedDaysAgo' => 3,  'updatedHoursAgo' => 2,  'owner' => 'AR', 'priority' => 'High'],
            'MR-26-04187' => ['title' => 'VVIP Lounge fit-out & FF&E',             'venue' => 'USA-SOF', 'site' => 'VVIP Lounge — Level 4',    'domain' => 'FFE', 'status' => 'submitted', 'items' => 42, 'value' => 96450,  'submittedDaysAgo' => 3,  'updatedHoursAgo' => 5,  'owner' => 'SO', 'priority' => 'High'],
            'MR-26-04186' => ['title' => 'Broadcast compound — primary distro',    'venue' => 'USA-MET', 'site' => 'Broadcast Compound',       'domain' => 'PWR', 'status' => 'finance',   'items' => 8,  'value' => 248600, 'submittedDaysAgo' => 4,  'updatedHoursAgo' => 24, 'owner' => 'TN', 'priority' => 'Critical'],
            'MR-26-04185' => ['title' => 'Volunteer Hub IT package',               'venue' => 'MEX-AZT', 'site' => 'Volunteer Hub — Gate C',   'domain' => 'IT',  'status' => 'approved',  'items' => 6,  'value' => 142880, 'submittedDaysAgo' => 5,  'updatedHoursAgo' => 24, 'owner' => 'JK', 'priority' => 'Medium'],
            'MR-26-04184' => ['title' => 'Catering tents — Hospitality cluster',   'venue' => 'CAN-BMO', 'site' => 'Catering Tent — H4',       'domain' => 'LOG', 'status' => 'changed',   'items' => 11, 'value' => 56720,  'submittedDaysAgo' => 5,  'updatedHoursAgo' => 24, 'owner' => 'LH', 'priority' => 'Medium'],
            'MR-26-04183' => ['title' => 'Anti-doping room — devices & displays', 'venue' => 'USA-ATT', 'site' => 'Anti-Doping — South',       'domain' => 'IT',  'status' => 'l1',        'items' => 9,  'value' => 28160,  'submittedDaysAgo' => 6,  'updatedHoursAgo' => 48, 'owner' => 'SO', 'priority' => 'Medium'],
            'MR-26-04182' => ['title' => 'Media Centre — workstations & AV',       'venue' => 'USA-NRG', 'site' => 'Media Centre',             'domain' => 'IT',  'status' => 'fulfilled', 'items' => 22, 'value' => 612400, 'submittedDaysAgo' => 7,  'updatedHoursAgo' => 72, 'owner' => 'JK', 'priority' => 'Critical'],
            'MR-26-04181' => ['title' => 'Operations Centre fencing perimeter',    'venue' => 'USA-MET', 'site' => 'Operations Centre',        'domain' => 'OVR', 'status' => 'rejected',  'items' => 5,  'value' => 14200,  'submittedDaysAgo' => 8,  'updatedHoursAgo' => 96, 'owner' => 'AR', 'priority' => 'Low'],
            'MR-26-04180' => ['title' => 'Press Tribune — additional seating',     'venue' => 'USA-SOF', 'site' => 'Press Tribune',            'domain' => 'LOG', 'status' => 'l2',        'items' => 3,  'value' => 21600,  'submittedDaysAgo' => 8,  'updatedHoursAgo' => 96, 'owner' => 'SO', 'priority' => 'Medium'],
            'MR-26-04179' => ['title' => 'Accreditation — backup power',          'venue' => 'MEX-AZT', 'site' => 'Accreditation Centre',     'domain' => 'PWR', 'status' => 'draft',     'items' => 4,  'value' => 38400,  'submittedDaysAgo' => null, 'updatedHoursAgo' => 120, 'owner' => 'AR', 'priority' => 'High'],
            'MR-26-04178' => ['title' => 'Medical clinic AV — match days',        'venue' => 'CAN-BMO', 'site' => 'Medical Clinic — Tier 1', 'domain' => 'IT',  'status' => 'approved',  'items' => 7,  'value' => 41200,  'submittedDaysAgo' => 10, 'updatedHoursAgo' => 144, 'owner' => 'JK', 'priority' => 'Medium'],
        ];

        $explicitLines = [
            'MR-26-04188' => [
                ['sku' => 'OV-FL-0420', 'qty' => 240, 'comment' => 'Per layout v3.1 — North end'],
                ['sku' => 'OV-FN-2200', 'qty' => 18, 'comment' => 'Crowd separation'],
                ['sku' => 'LG-TN-1010', 'qty' => 2, 'comment' => 'Broadcaster waiting'],
                ['sku' => 'LG-FN-0312', 'qty' => 18, 'comment' => 'Athlete bench'],
                ['sku' => 'IT-AV-0102', 'qty' => 4, 'comment' => 'Run-of-show feed'],
                ['sku' => 'IT-AV-0411', 'qty' => 4, 'comment' => 'Press scrum'],
            ],
        ];

        $notes = ['Per site layout', 'Confirmed with venue ops', 'Peak-day requirement', 'Spare pool included'];

        // Bundles are no longer scoped to a catalog item, so seeded lines just
        // draw from whichever bundles are marked default rather than "the
        // default option for this SKU".
        $defaultOptions = ServiceOption::where('is_default', true)->get();

        $requests = [];
        foreach ($headers as $mockId => $h) {
            $request = MaterialRequest::create([
                'code' => $mockId,
                'title' => $h['title'],
                'event_id' => $eventId,
                'venue_id' => $venueIds[self::VENUE_MAP[$h['venue']]],
                'site_name' => $h['site'],
                'priority' => $h['priority'],
                'status' => $h['status'],
                'owner_user_id' => $people[$h['owner']]->id,
                'submitted_at' => $h['submittedDaysAgo'] !== null ? now()->subDays($h['submittedDaysAgo']) : null,
                'notes' => 'Footprint confirmed with overlay plan. Coordinate with broadcast for cable runs.',
            ]);
            $request->timestamps = false;
            $request->updated_at = now()->subHours($h['updatedHoursAgo']);
            $request->save();
            $request->timestamps = true;

            if (isset($explicitLines[$mockId])) {
                foreach ($explicitLines[$mockId] as $l) {
                    $item = $catalog[$l['sku']];
                    $option = $defaultOptions->isNotEmpty() ? $defaultOptions->random() : null;
                    $request->lines()->create([
                        'catalog_item_id' => $item->id, 'qty' => $l['qty'], 'rate_snapshot' => $item->rate,
                        'comment' => $l['comment'], 'service_option_id' => $option?->id,
                    ]);
                }
            } else {
                $pool = array_values(array_filter($catalog, fn (CatalogItem $c) => $c->domain_code === $h['domain']));
                $n = max(1, min($h['items'], count($pool)));
                $share = $h['value'] / $n;
                foreach (array_slice($pool, 0, $n) as $i => $item) {
                    $qty = (int) max(1, min(round($share / $item->rate), round(($item->baseline ?: 40) * 0.75)));
                    $option = $defaultOptions->isNotEmpty() ? $defaultOptions->random() : null;
                    $request->lines()->create([
                        'catalog_item_id' => $item->id, 'qty' => $qty, 'rate_snapshot' => $item->rate,
                        'comment' => $notes[$i % count($notes)], 'service_option_id' => $option?->id,
                    ]);
                }
            }

            $this->seedApprovalChain($request, $people);
            $requests[$mockId] = $request;
        }

        return $requests;
    }

    private function seedApprovalChain(MaterialRequest $request, array $people): void
    {
        if ($request->status === 'draft') {
            $request->activity()->create(['actor_user_id' => $request->owner_user_id, 'verb' => 'created draft']);
            return;
        }

        $doneStates = ['submitted', 'l1', 'l2', 'finance', 'approved', 'changed', 'rejected', 'fulfilled'];
        $isDone = in_array($request->status, $doneStates, true);

        $request->approvalSteps()->createMany([
            ['step_no' => 1, 'role_label' => 'Venue Planner', 'approver_user_id' => $request->owner_user_id, 'state' => 'done', 'acted_at' => $request->submitted_at],
            ['step_no' => 2, 'role_label' => 'L1 — Site Coordinator', 'approver_user_id' => $people['SO']->id, 'state' => in_array($request->status, ['l1'], true) ? 'now' : ($isDone && $request->status !== 'submitted' ? 'done' : 'next')],
            ['step_no' => 3, 'role_label' => 'Category Lead', 'approver_user_id' => $people['MC']->id, 'state' => $request->status === 'l2' ? 'now' : (in_array($request->status, ['finance', 'approved', 'fulfilled'], true) ? 'done' : 'next')],
            ['step_no' => 4, 'role_label' => 'Finance Controller (auto, value > $50k)', 'approver_user_id' => $people['DV']->id, 'state' => $request->status === 'finance' ? 'now' : (in_array($request->status, ['approved', 'fulfilled'], true) ? 'done' : 'next')],
        ]);

        $request->activity()->create([
            'actor_user_id' => $request->owner_user_id,
            'verb' => 'submitted',
            'subject' => 'v1',
            'note' => $request->items . ' line items',
        ]);
    }

    /** @param array<string, MaterialRequest> $requests, array<string, CatalogItem> $catalog */
    private function seedChangeOrders(array $requests, array $catalog, array $people): void
    {
        // reason => qty factor applied to a real line already on the request
        $rows = [
            ['req' => 'MR-26-04186', 'context' => 'broadcast feed redundancy', 'reason' => 'Scope increase',        'raisedBy' => 'TN', 'daysAgo' => 1, 'state' => 'pending',  'stage' => 'Finance'],
            ['req' => 'MR-26-04184', 'context' => 'hospitality flow rework',   'reason' => 'Overlay revision',      'raisedBy' => 'LH', 'daysAgo' => 1, 'state' => 'pending',  'stage' => 'Category'],
            ['req' => 'MR-26-04187', 'context' => 'lounge footprint cut',      'reason' => 'Scope reduction',       'raisedBy' => 'SO', 'daysAgo' => 2, 'state' => 'pending',  'stage' => 'Category'],
            ['req' => 'MR-26-04180', 'context' => 'tier-2 press capacity',     'reason' => 'Client request',        'raisedBy' => 'SO', 'daysAgo' => 2, 'state' => 'draft',    'stage' => 'Not submitted'],
            ['req' => 'MR-26-04188', 'context' => 'overlay plan correction',   'reason' => 'Overlay revision',      'raisedBy' => 'MC', 'daysAgo' => 1, 'state' => 'approved', 'stage' => 'Applied'],
            ['req' => 'MR-26-04178', 'context' => 'wi-fi supplier switch',     'reason' => 'Service option change', 'raisedBy' => 'JK', 'daysAgo' => 1, 'state' => 'pending',  'stage' => 'Category'],
            ['req' => 'MR-26-04185', 'context' => 'supply constraint',        'reason' => 'Service option change', 'raisedBy' => 'JK', 'daysAgo' => 3, 'state' => 'approved', 'stage' => 'Applied'],
            ['req' => 'MR-26-04182', 'context' => 'press growth',             'reason' => 'Scope increase',        'raisedBy' => 'JK', 'daysAgo' => 4, 'state' => 'approved', 'stage' => 'Applied'],
            ['req' => 'MR-26-04183', 'context' => 'domain budget hold',       'reason' => 'Budget correction',     'raisedBy' => 'DV', 'daysAgo' => 4, 'state' => 'approved', 'stage' => 'Applied'],
            ['req' => 'MR-26-04181', 'context' => 'crowd separation',         'reason' => 'Scope increase',        'raisedBy' => 'AR', 'daysAgo' => 6, 'state' => 'rejected', 'stage' => 'Declined'],
            ['req' => 'MR-26-04178', 'context' => 'match-day spares',         'reason' => 'Scope increase',        'raisedBy' => 'JK', 'daysAgo' => 7, 'state' => 'approved', 'stage' => 'Applied'],
        ];

        $factors = [
            'Scope increase' => 1.3, 'Scope reduction' => 0.6, 'Overlay revision' => 1.2,
            'Budget correction' => 0.7, 'Client request' => 1.15, 'Service option change' => 1.0,
        ];

        foreach ($rows as $row) {
            $request = $requests[$row['req']];
            $lines = $request->lines()->with('catalogItem')->get();
            if ($lines->isEmpty()) {
                continue;
            }

            $changeOrder = ChangeOrder::create([
                'request_id' => $request->id,
                'context' => $row['context'],
                'reason' => $row['reason'],
                'raised_by_user_id' => $people[$row['raisedBy']]->id,
                'raised_on' => now()->subDays($row['daysAgo']),
                'state' => $row['state'],
                'stage' => $row['stage'],
            ]);

            $line = $lines->sortByDesc(fn ($l) => $l->qty * $l->rate_snapshot)->first();

            if ($row['reason'] === 'Service option change') {
                $altOption = ServiceOption::with('services')
                    ->where('id', '!=', $line->service_option_id)
                    ->where('classification_id', '!=', 3)
                    ->inRandomOrder()
                    ->first();
                $altCost = $altOption ? $altOption->services->sum('cost') : null;
                $changeOrder->lines()->create([
                    'request_line_id' => $line->id, 'catalog_item_id' => $line->catalog_item_id,
                    'qty_before' => $line->qty, 'qty_after' => $line->qty,
                    'rate_before' => $line->rate_snapshot, 'rate_after' => $altCost ?? $line->rate_snapshot,
                    'service_option_before_id' => $line->service_option_id, 'service_option_after_id' => $altOption?->id,
                    'why' => 'Delivery switched to an alternative supplier option',
                ]);
            } elseif ($row['reason'] === 'Client request') {
                $item = $catalog[$line->sku];
                $addQty = max(1, (int) round($line->qty * 0.1));
                $changeOrder->lines()->create([
                    'request_line_id' => null, 'catalog_item_id' => $line->catalog_item_id,
                    'qty_before' => null, 'qty_after' => $addQty,
                    'rate_before' => null, 'rate_after' => $item->rate,
                    'why' => 'Requested by the venue operations centre',
                ]);
            } else {
                $factor = $factors[$row['reason']];
                $newQty = $this->applyFactor($line->qty, $factor);
                $changeOrder->lines()->create([
                    'request_line_id' => $line->id, 'catalog_item_id' => $line->catalog_item_id,
                    'qty_before' => $line->qty, 'qty_after' => $newQty,
                    'rate_before' => $line->rate_snapshot, 'rate_after' => $line->rate_snapshot,
                    'why' => 'Quantity revised against the latest site plan',
                ]);

                // A second, smaller line for change orders the mock showed as multi-row.
                if ($lines->count() > 1 && in_array($row['req'], ['MR-26-04186', 'MR-26-04184', 'MR-26-04188', 'MR-26-04182', 'MR-26-04181'], true)) {
                    $second = $lines->where('id', '!=', $line->id)->first();
                    $newQty2 = $this->applyFactor($second->qty, $factor);
                    $changeOrder->lines()->create([
                        'request_line_id' => $second->id, 'catalog_item_id' => $second->catalog_item_id,
                        'qty_before' => $second->qty, 'qty_after' => $newQty2,
                        'rate_before' => $second->rate_snapshot, 'rate_after' => $second->rate_snapshot,
                        'why' => 'Quantity revised against the latest site plan',
                    ]);
                }
            }

            if ($row['state'] === 'approved') {
                foreach ($changeOrder->lines as $coLine) {
                    if ($coLine->request_line_id) {
                        $coLine->requestLine->update([
                            'qty' => $coLine->qty_after ?? $coLine->requestLine->qty,
                            'service_option_id' => $coLine->service_option_after_id ?? $coLine->requestLine->service_option_id,
                        ]);
                    }
                }
            }
        }
    }

    /** Applies a scale factor to a qty, guaranteeing a visible change even for small quantities. */
    private function applyFactor(int $qty, float $factor): int
    {
        $result = (int) round($qty * $factor);
        if ($factor > 1 && $result <= $qty) {
            $result = $qty + 1;
        } elseif ($factor < 1 && $result >= $qty) {
            $result = max(0, $qty - 1);
        }
        return max(0, $result);
    }
}
