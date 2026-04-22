<?php
return [
    // ——— /api/user/projects endpoints ——————————————————————————————
    'not_found'         => 'Project not found.',
    'url_required'      => 'A valid URL is required.',
    'url_invalid'       => 'The URL is invalid.',
    'name_required'     => 'Project name is required.',
    'fetch_error'       => 'Failed to load projects.',
    'create_error'      => 'Failed to create the project.',
    'update_error'      => 'Failed to update the project.',
    'delete_error'      => 'Failed to delete the project.',
    'url_duplicate'     => 'You already have a project for this URL.',
    'quota_projects'    => 'You have reached your plan\'s project limit ({{used}}/{{limit}}). Upgrade your plan or delete a project.',
    'no_plan'           => 'Your account has no plan assigned. Projects are not available.',
    'not_owner'         => 'You don\'t have access to this project.',

    // ——— Checklist ————————————————————————————————————————————————
    'checklist.fetch_error'      => 'Failed to load the checklist.',
    'checklist.item_not_found'   => 'Checklist item not found.',
    'checklist.status_invalid'   => 'Invalid status. Must be open, done or ignored.',

    // ——— Share links ——————————————————————————————————————————————
    'share.invalid_token'  => 'This shared link is invalid or has been revoked.',
    'share.toggle_error'   => 'Failed to change the share setting.',
    'share.not_enabled'    => 'Sharing is not enabled for this project.',
];
