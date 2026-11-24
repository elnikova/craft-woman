<?php

return [
    'category' => [
        'has_children' => 'Category ":name" has subcategories. Move or delete them first.',
        'in_use' => 'Category ":name" is in use (:count) and cannot be deleted. Hide it instead.',
        'too_deep' => 'Categories have two levels: a subcategory cannot have its own subcategories.',
        'parent_with_children' => 'A category that has subcategories cannot become a subcategory.',
        'self_parent' => 'A category cannot be its own parent.',
    ],
    'maker' => [
        'invalid_transition' => 'This action is not available for a profile in ":from" status.',
        'not_editable' => 'A profile in ":status" status cannot be edited.',
        'already_exists' => 'You already have a maker profile.',
    ],
    'statuses' => [
        'draft' => 'draft',
        'pending_review' => 'pending review',
        'approved' => 'published',
        'rejected' => 'changes requested',
        'suspended' => 'suspended',
    ],
];
