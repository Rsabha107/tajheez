<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class MaterialPlanningController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('MaterialPlanning/Index', [
            'event' => [
                'code'    => 'FWC26',
                'name'    => 'FIFA World Cup 2026',
                'window'  => 'Jun 11 – Jul 19, 2026',
                'daysOut' => 27,
            ],
            'venues' => [
                ['code' => 'MEX-AZT', 'name' => 'Estadio Azteca',   'city' => 'Mexico City',     'sites' => 184],
                ['code' => 'USA-MET', 'name' => 'MetLife Stadium',   'city' => 'East Rutherford', 'sites' => 171],
                ['code' => 'USA-SOF', 'name' => 'SoFi Stadium',      'city' => 'Inglewood',       'sites' => 162],
                ['code' => 'USA-ATT', 'name' => 'AT&T Stadium',      'city' => 'Arlington',       'sites' => 156],
                ['code' => 'CAN-BMO', 'name' => 'BMO Field',         'city' => 'Toronto',         'sites' => 128],
                ['code' => 'USA-NRG', 'name' => 'NRG Stadium',       'city' => 'Houston',         'sites' => 147],
            ],
            'domains' => [
                ['id' => 'IT',  'label' => 'IT',         'color' => '#1d4ed8', 'chip' => '#dbeafe', 'desc' => 'Network, AV, devices'],
                ['id' => 'LOG', 'label' => 'Logistics',  'color' => '#7c2d12', 'chip' => '#fde7d3', 'desc' => 'Furniture, tents, signage'],
                ['id' => 'PWR', 'label' => 'Power',      'color' => '#b45309', 'chip' => '#fef3c7', 'desc' => 'Generators, distro, cabling'],
                ['id' => 'OVR', 'label' => 'Overlay',    'color' => '#0f766e', 'chip' => '#ccfbf1', 'desc' => 'Fencing, flooring, structures'],
                ['id' => 'FFE', 'label' => 'FF&E',       'color' => '#6b21a8', 'chip' => '#ede9fe', 'desc' => 'Furniture, fittings & equipment'],
            ],
            'statuses' => [
                'draft'     => ['label' => 'Draft',      'dot' => '#9ca3af', 'bg' => '#f3f4f6', 'fg' => '#374151'],
                'submitted' => ['label' => 'Submitted',  'dot' => '#1d4ed8', 'bg' => '#dbeafe', 'fg' => '#1e3a8a'],
                'l1'        => ['label' => 'L1 Review',  'dot' => '#b45309', 'bg' => '#fef3c7', 'fg' => '#92400e'],
                'l2'        => ['label' => 'Category',   'dot' => '#b45309', 'bg' => '#fef3c7', 'fg' => '#92400e'],
                'finance'   => ['label' => 'Finance',    'dot' => '#b45309', 'bg' => '#fef3c7', 'fg' => '#92400e'],
                'approved'  => ['label' => 'Approved',   'dot' => '#166534', 'bg' => '#dcfce7', 'fg' => '#14532d'],
                'changed'   => ['label' => 'Change Req', 'dot' => '#7c2d12', 'bg' => '#fde7d3', 'fg' => '#7c2d12'],
                'rejected'  => ['label' => 'Rejected',   'dot' => '#991b1b', 'bg' => '#fee2e2', 'fg' => '#7f1d1d'],
                'fulfilled' => ['label' => 'Fulfilled',  'dot' => '#0f766e', 'bg' => '#ccfbf1', 'fg' => '#134e4a'],
            ],
            'people' => [
                ['initials' => 'AR', 'name' => 'Amal Rashid',   'role' => 'Venue Planner',          'org' => 'USA-MET'],
                ['initials' => 'JK', 'name' => 'Jordan Kim',    'role' => 'Category Lead — IT',      'org' => 'HQ'],
                ['initials' => 'MC', 'name' => 'Marta Conti',   'role' => 'Category Lead — Overlay', 'org' => 'HQ'],
                ['initials' => 'DV', 'name' => 'Diego Vargas',  'role' => 'Finance Controller',      'org' => 'HQ'],
                ['initials' => 'SO', 'name' => 'Sara Othman',   'role' => 'Site Coordinator',        'org' => 'USA-SOF'],
                ['initials' => 'TN', 'name' => 'Tariq Nasir',   'role' => 'Power Lead',              'org' => 'HQ'],
                ['initials' => 'LH', 'name' => 'Lukas Hofer',   'role' => 'Logistics Manager',       'org' => 'USA-ATT'],
            ],
            'requests' => [
                ['id' => 'MR-26-04188', 'title' => 'Mixed Zone build-out — Media tier 1',      'venue' => 'USA-MET', 'site' => 'Mixed Zone — North',      'domain' => 'OVR', 'status' => 'l2',        'items' => 14, 'qty' => 286, 'value' => 184320, 'submitted' => 'Apr 22', 'updated' => '2h ago',  'owner' => 'AR', 'priority' => 'High'],
                ['id' => 'MR-26-04187', 'title' => 'VVIP Lounge fit-out & FF&E',               'venue' => 'USA-SOF', 'site' => 'VVIP Lounge — Level 4',   'domain' => 'FFE', 'status' => 'submitted', 'items' => 42, 'qty' => 188, 'value' => 96450,  'submitted' => 'Apr 22', 'updated' => '5h ago',  'owner' => 'SO', 'priority' => 'High'],
                ['id' => 'MR-26-04186', 'title' => 'Broadcast compound — primary distro',      'venue' => 'USA-MET', 'site' => 'Broadcast Compound',       'domain' => 'PWR', 'status' => 'finance',   'items' => 8,  'qty' => 36,  'value' => 248600, 'submitted' => 'Apr 21', 'updated' => '1d ago',  'owner' => 'TN', 'priority' => 'Critical'],
                ['id' => 'MR-26-04185', 'title' => 'Volunteer Hub IT package',                 'venue' => 'MEX-AZT', 'site' => 'Volunteer Hub — Gate C',   'domain' => 'IT',  'status' => 'approved',  'items' => 6,  'qty' => 312, 'value' => 142880, 'submitted' => 'Apr 20', 'updated' => '1d ago',  'owner' => 'JK', 'priority' => 'Medium'],
                ['id' => 'MR-26-04184', 'title' => 'Catering tents — Hospitality cluster',     'venue' => 'CAN-BMO', 'site' => 'Catering Tent — H4',       'domain' => 'LOG', 'status' => 'changed',   'items' => 11, 'qty' => 62,  'value' => 56720,  'submitted' => 'Apr 20', 'updated' => '1d ago',  'owner' => 'LH', 'priority' => 'Medium'],
                ['id' => 'MR-26-04183', 'title' => 'Anti-doping room — devices & displays',   'venue' => 'USA-ATT', 'site' => 'Anti-Doping — South',       'domain' => 'IT',  'status' => 'l1',        'items' => 9,  'qty' => 24,  'value' => 28160,  'submitted' => 'Apr 19', 'updated' => '2d ago',  'owner' => 'SO', 'priority' => 'Medium'],
                ['id' => 'MR-26-04182', 'title' => 'Media Centre — workstations & AV',         'venue' => 'USA-NRG', 'site' => 'Media Centre',             'domain' => 'IT',  'status' => 'fulfilled', 'items' => 22, 'qty' => 484, 'value' => 612400, 'submitted' => 'Apr 18', 'updated' => '3d ago',  'owner' => 'JK', 'priority' => 'Critical'],
                ['id' => 'MR-26-04181', 'title' => 'Operations Centre fencing perimeter',      'venue' => 'USA-MET', 'site' => 'Operations Centre',        'domain' => 'OVR', 'status' => 'rejected',  'items' => 5,  'qty' => 184, 'value' => 14200,  'submitted' => 'Apr 17', 'updated' => '4d ago',  'owner' => 'AR', 'priority' => 'Low'],
                ['id' => 'MR-26-04180', 'title' => 'Press Tribune — additional seating',       'venue' => 'USA-SOF', 'site' => 'Press Tribune',            'domain' => 'LOG', 'status' => 'l2',        'items' => 3,  'qty' => 240, 'value' => 21600,  'submitted' => 'Apr 17', 'updated' => '4d ago',  'owner' => 'SO', 'priority' => 'Medium'],
                ['id' => 'MR-26-04179', 'title' => 'Accreditation — backup power',             'venue' => 'MEX-AZT', 'site' => 'Accreditation Centre',     'domain' => 'PWR', 'status' => 'draft',     'items' => 4,  'qty' => 6,   'value' => 38400,  'submitted' => '—',       'updated' => '5d ago',  'owner' => 'AR', 'priority' => 'High'],
                ['id' => 'MR-26-04178', 'title' => 'Medical clinic AV — match days',           'venue' => 'CAN-BMO', 'site' => 'Medical Clinic — Tier 1', 'domain' => 'IT',  'status' => 'approved',  'items' => 7,  'qty' => 32,  'value' => 41200,  'submitted' => 'Apr 15', 'updated' => '6d ago',  'owner' => 'JK', 'priority' => 'Medium'],
            ],
            'catalog' => [
                ['sku' => 'IT-NW-0214', 'domain' => 'IT',  'group' => 'Network',    'sub' => 'Switches',      'name' => 'Cisco Catalyst 9300-48P',          'unit' => 'ea',   'rate' => 6240,  'stock' => 184, 'baseline' => 240],
                ['sku' => 'IT-NW-0301', 'domain' => 'IT',  'group' => 'Network',    'sub' => 'Access Points', 'name' => 'Aruba AP-635 Wi-Fi 6E',            'unit' => 'ea',   'rate' => 820,   'stock' => 512, 'baseline' => 640],
                ['sku' => 'IT-AV-0102', 'domain' => 'IT',  'group' => 'AV',         'sub' => 'Displays',      'name' => 'Samsung 75" QM75B Commercial',      'unit' => 'ea',   'rate' => 2950,  'stock' => 96,  'baseline' => 120],
                ['sku' => 'IT-AV-0411', 'domain' => 'IT',  'group' => 'AV',         'sub' => 'Microphones',   'name' => 'Shure ULXD24/SM58 Wireless',        'unit' => 'ea',   'rate' => 1840,  'stock' => 72,  'baseline' => 80],
                ['sku' => 'IT-DV-0220', 'domain' => 'IT',  'group' => 'Devices',    'sub' => 'Laptops',       'name' => 'Dell Latitude 5450 i7/16/512',      'unit' => 'ea',   'rate' => 1690,  'stock' => 640, 'baseline' => 720],
                ['sku' => 'IT-DV-0044', 'domain' => 'IT',  'group' => 'Devices',    'sub' => 'Tablets',       'name' => 'iPad 10.9" Wi-Fi 64GB',             'unit' => 'ea',   'rate' => 430,   'stock' => 380, 'baseline' => 420],
                ['sku' => 'LG-TN-0610', 'domain' => 'LOG', 'group' => 'Tents',      'sub' => 'Structures',    'name' => 'Standard Tent 6×6 m w/ Floor',      'unit' => 'ea',   'rate' => 2400,  'stock' => 144, 'baseline' => 180],
                ['sku' => 'LG-TN-1010', 'domain' => 'LOG', 'group' => 'Tents',      'sub' => 'Structures',    'name' => 'Standard Tent 10×10 m w/ Floor',    'unit' => 'ea',   'rate' => 5180,  'stock' => 96,  'baseline' => 120],
                ['sku' => 'LG-FN-0312', 'domain' => 'LOG', 'group' => 'Furniture',  'sub' => 'Seating',       'name' => 'Stacking Chair – Black Vinyl',       'unit' => 'ea',   'rate' => 28,    'stock' => 4800,'baseline' => 5200],
                ['sku' => 'LG-FN-0420', 'domain' => 'LOG', 'group' => 'Furniture',  'sub' => 'Tables',        'name' => '6 ft Banquet Table',                'unit' => 'ea',   'rate' => 62,    'stock' => 1200,'baseline' => 1400],
                ['sku' => 'LG-SG-0188', 'domain' => 'LOG', 'group' => 'Signage',    'sub' => 'Wayfinding',    'name' => 'A1 Wayfinding Stand, Double-sided',  'unit' => 'ea',   'rate' => 88,    'stock' => 380, 'baseline' => 520],
                ['sku' => 'PW-GN-0500', 'domain' => 'PWR', 'group' => 'Generators', 'sub' => 'Diesel',        'name' => 'Cummins C500D6 500 kVA Generator',   'unit' => 'ea',   'rate' => 18400, 'stock' => 24,  'baseline' => 32],
                ['sku' => 'PW-GN-0125', 'domain' => 'PWR', 'group' => 'Generators', 'sub' => 'Diesel',        'name' => 'Cummins C125D5 125 kVA Generator',   'unit' => 'ea',   'rate' => 6800,  'stock' => 48,  'baseline' => 60],
                ['sku' => 'PW-DT-0063', 'domain' => 'PWR', 'group' => 'Distro',     'sub' => 'Panels',        'name' => '63 A 3-phase Distro Panel',          'unit' => 'ea',   'rate' => 740,   'stock' => 120, 'baseline' => 160],
                ['sku' => 'PW-CB-1632', 'domain' => 'PWR', 'group' => 'Cabling',    'sub' => 'Power Cable',   'name' => '32 A Cable, 25 m',                  'unit' => 'ea',   'rate' => 140,   'stock' => 640, 'baseline' => 800],
                ['sku' => 'OV-FL-0420', 'domain' => 'OVR', 'group' => 'Flooring',   'sub' => 'Modular',       'name' => 'Modular Event Flooring 0.5×0.5 m',   'unit' => 'm²',   'rate' => 42,    'stock' => 3200,'baseline' => 4000],
                ['sku' => 'OV-FN-2200', 'domain' => 'OVR', 'group' => 'Fencing',    'sub' => 'Heras',         'name' => 'Heras Fence Panel 3.5 m',            'unit' => 'ea',   'rate' => 36,    'stock' => 1800,'baseline' => 2200],
                ['sku' => 'OV-ST-0102', 'domain' => 'OVR', 'group' => 'Structures', 'sub' => 'Bleachers',     'name' => 'Modular Bleacher – 200 seat',         'unit' => 'unit', 'rate' => 14800, 'stock' => 8,   'baseline' => 12],
                ['sku' => 'FE-FN-0101', 'domain' => 'FFE', 'group' => 'Furniture',  'sub' => 'Lounge',        'name' => 'Lounge Sofa – Charcoal Linen',       'unit' => 'ea',   'rate' => 480,   'stock' => 96,  'baseline' => 120],
                ['sku' => 'FE-FT-0044', 'domain' => 'FFE', 'group' => 'Fittings',   'sub' => 'Lighting',      'name' => 'Standing Floor Lamp, brass',         'unit' => 'ea',   'rate' => 140,   'stock' => 160, 'baseline' => 200],
            ],
        ]);
    }
}
