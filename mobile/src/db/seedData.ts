import type { ItemType, TeamLeader, TemplateItem } from '../types';

export const SEED_CATEGORIES: string[] = [
  'General Safety',
  'Architectural Works',
  'Civil / Structural Works',
  'Electrical Works',
  'Mechanical Works',
  'Sanitary / Plumbing Works',
  'Electronics Works',
];

export const SEED_TEMPLATE: Record<string, Omit<TemplateItem, 'id'>[]> = {
  'General Safety': [
    { category: 'General Safety', item_text: 'Provided signage and barricades are in place', item_type: 'checkbox', sort_order: 0 },
    { category: 'General Safety', item_text: 'Personal protective equipment (PPE) is being worn by workers', item_type: 'checkbox', sort_order: 1 },
    { category: 'General Safety', item_text: 'Presence of first-aid kits', item_type: 'checkbox', sort_order: 2 },
    { category: 'General Safety', item_text: 'Scaffoldings and ladders are secure and in good condition', item_type: 'checkbox', sort_order: 3 },
  ],
  'Architectural Works': [
    { category: 'Architectural Works', item_text: 'Firewall', item_type: 'checkbox', sort_order: 0 },
    { category: 'Architectural Works', item_text: 'Parking', item_type: 'checkbox', sort_order: 1 },
    { category: 'Architectural Works', item_text: 'PWD Ramp/Railing', item_type: 'checkbox', sort_order: 2 },
    { category: 'Architectural Works', item_text: 'PWD CR/Utilities', item_type: 'checkbox', sort_order: 3 },
    { category: 'Architectural Works', item_text: 'Fire Exit', item_type: 'checkbox', sort_order: 4 },
  ],
  'Civil / Structural Works': [
    { category: 'Civil / Structural Works', item_text: 'Column Footings', item_type: 'checkbox', sort_order: 0 },
    { category: 'Civil / Structural Works', item_text: 'Wall Footings', item_type: 'checkbox', sort_order: 1 },
    { category: 'Civil / Structural Works', item_text: 'Tie Beams', item_type: 'checkbox', sort_order: 2 },
    { category: 'Civil / Structural Works', item_text: 'Columns', item_type: 'checkbox', sort_order: 3 },
    { category: 'Civil / Structural Works', item_text: 'Beams', item_type: 'checkbox', sort_order: 4 },
    { category: 'Civil / Structural Works', item_text: 'Girders', item_type: 'checkbox', sort_order: 5 },
    { category: 'Civil / Structural Works', item_text: 'Slabs', item_type: 'checkbox', sort_order: 6 },
    { category: 'Civil / Structural Works', item_text: 'Stairs', item_type: 'checkbox', sort_order: 7 },
    { category: 'Civil / Structural Works', item_text: 'Roof Beams', item_type: 'checkbox', sort_order: 8 },
    { category: 'Civil / Structural Works', item_text: 'Truss', item_type: 'checkbox', sort_order: 9 },
    { category: 'Civil / Structural Works', item_text: 'Others', item_type: 'checkbox', sort_order: 10 },
  ],
  'Electrical Works': [
    { category: 'Electrical Works', item_text: 'Installed electrical devices as per the approved plan', item_type: 'checkbox', sort_order: 0 },
    { category: 'Electrical Works', item_text: 'Sizes of the conductor as per the approved plan', item_type: 'checkbox', sort_order: 1 },
    { category: 'Electrical Works', item_text: 'Installed protection devices as per the approved plan', item_type: 'checkbox', sort_order: 2 },
    { category: 'Electrical Works', item_text: 'Installed equipment grounding conductor (rod)', item_type: 'checkbox', sort_order: 3 },
  ],
  'Mechanical Works': [
    { category: 'Mechanical Works', item_text: 'Installed HVAC, ducting air conditioning as per approved plan', item_type: 'checkbox', sort_order: 0 },
    { category: 'Mechanical Works', item_text: 'Ceiling/Wall/Floor mounted aircon as per approved plan', item_type: 'checkbox', sort_order: 1 },
  ],
  'Sanitary / Plumbing Works': [
    { category: 'Sanitary / Plumbing Works', item_text: 'Roughing pipe layout as per approved plan', item_type: 'checkbox', sort_order: 0 },
    { category: 'Sanitary / Plumbing Works', item_text: 'Installed plumbing fixtures as per approved plan', item_type: 'checkbox', sort_order: 1 },
    { category: 'Sanitary / Plumbing Works', item_text: 'Septic Vault as per approved plan', item_type: 'checkbox', sort_order: 2 },
  ],
  'Electronics Works': [
    { category: 'Electronics Works', item_text: 'Layout electronics wiring as per approved plan', item_type: 'checkbox', sort_order: 0 },
    { category: 'Electronics Works', item_text: 'Installed electronics devices as per approved plan', item_type: 'checkbox', sort_order: 1 },
  ],
};

export const SEED_TEMPLATE_ITEMS: TemplateItem[] = SEED_CATEGORIES.flatMap((cat, catIdx) =>
  (SEED_TEMPLATE[cat] ?? []).map((it, itemIdx) => ({
    ...it,
    id: catIdx * 100 + itemIdx + 1,
  })),
);

export const SEED_TEAM_LEADERS: TeamLeader[] = [
  { id: 1, full_name: 'Carlos Mendoza', position: 'Team Leader', team_no: 1 },
  { id: 2, full_name: 'Rosa Villanueva', position: 'Team Leader', team_no: 2 },
];

export type TemplateItemType = ItemType;
