-- Backfill Accepted GOP In for historical files (service_date < 2026-07-07).
-- Run preview SELECTs first. Then run the UPDATE, then the INSERT.

-- 1) Preview: files missing Accepted GOP In
SELECT f.id, f.mga_reference, f.service_date, f.status
FROM files f
WHERE f.service_date IS NOT NULL
  AND f.service_date < '2026-07-07'
  AND NOT EXISTS (
      SELECT 1
      FROM gops g
      WHERE g.file_id = f.id
        AND g.type = 'In'
        AND g.status = 'Accepted'
  )
ORDER BY f.id;

-- 2) Update latest existing GOP In → Accepted (files that already have an In GOP)
UPDATE gops g
INNER JOIN (
    SELECT g2.file_id, MAX(g2.id) AS gop_id
    FROM gops g2
    INNER JOIN files f ON f.id = g2.file_id
    WHERE g2.type = 'In'
      AND f.service_date IS NOT NULL
      AND f.service_date < '2026-07-07'
      AND NOT EXISTS (
          SELECT 1
          FROM gops ax
          WHERE ax.file_id = g2.file_id
            AND ax.type = 'In'
            AND ax.status = 'Accepted'
      )
    GROUP BY g2.file_id
) t ON t.gop_id = g.id
SET g.status = 'Accepted',
    g.updated_at = NOW();

-- 3) Insert Accepted GOP In for files with no type=In GOP at all
INSERT INTO gops (
    file_id,
    provider_branch_id,
    service_type_id,
    type,
    status,
    amount,
    offered_cost,
    file_fee,
    notes,
    date,
    gop_google_drive_link,
    document_path,
    created_at,
    updated_at
)
SELECT
    f.id,
    f.provider_branch_id,
    f.service_type_id,
    'In',
    'Accepted',
    0,
    0,
    0,
    'Backfilled Accepted GOP In for historical file (service_date before cutoff).',
    COALESCE(f.service_date, CURDATE()),
    NULL,
    NULL,
    NOW(),
    NOW()
FROM files f
WHERE f.service_date IS NOT NULL
  AND f.service_date < '2026-07-07'
  AND NOT EXISTS (
      SELECT 1
      FROM gops g
      WHERE g.file_id = f.id
        AND g.type = 'In'
  );
