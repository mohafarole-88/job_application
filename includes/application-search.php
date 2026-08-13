<?php
/**
 * includes/application-search.php
 * Shared WHERE-clause builder for filtering applications by search
 * term / status / position. Used by both admin/dashboard.php (the
 * paginated list) and admin/download-all-applications.php (the bulk
 * zip export), so "what the list shows" and "what the zip contains"
 * can never quietly drift apart from each other.
 */

const APPLICATION_VALID_STATUSES = ['submitted', 'reviewed', 'shortlisted', 'rejected', 'archived'];

/**
 * @param array $queryParams Typically $_GET
 * @return array{search:string, status:string, position:string, whereSql:string, params:array}
 */
function build_application_filters(array $queryParams): array
{
    $search   = trim((string) ($queryParams['q'] ?? ''));
    $status   = trim((string) ($queryParams['status'] ?? ''));
    $position = trim((string) ($queryParams['position'] ?? ''));

    if ($status !== '' && !in_array($status, APPLICATION_VALID_STATUSES, true)) {
        $status = '';
    }

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(first_name LIKE :search1 OR surname LIKE :search2 OR application_number LIKE :search3 OR email LIKE :search4)';
        $like = '%' . $search . '%';
        $params['search1'] = $like;
        $params['search2'] = $like;
        $params['search3'] = $like;
        $params['search4'] = $like;
    }
    if ($status !== '') {
        $where[] = 'status = :status';
        $params['status'] = $status;
    }
    if ($position !== '') {
        $where[] = 'position_applied LIKE :position';
        $params['position'] = '%' . $position . '%';
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    return [
        'search'   => $search,
        'status'   => $status,
        'position' => $position,
        'whereSql' => $whereSql,
        'params'   => $params,
    ];
}
