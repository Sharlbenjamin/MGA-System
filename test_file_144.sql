-- Test File ID 144 and analyze filtering relationships

-- 1. Get file details
SELECT 
    f.id,
    p.name as patient_name,
    st.name as service_type,
    st.id as service_type_id,
    c.name as city,
    c.id as city_id,
    co.name as country,
    co.id as country_id,
    f.address
FROM files f
LEFT JOIN patients p ON f.patient_id = p.id
LEFT JOIN service_types st ON f.service_type_id = st.id
LEFT JOIN cities c ON f.city_id = c.id
LEFT JOIN countries co ON f.country_id = co.id
WHERE f.id = 144;

-- 2. Get all providers with branches in the file's country
SELECT 
    p.id as provider_id,
    p.name as provider_name,
    p.status as provider_status,
    p.country_id,
    COUNT(pb.id) as branch_count
FROM providers p
INNER JOIN provider_branches pb ON p.id = pb.provider_id
WHERE p.country_id = (SELECT country_id FROM files WHERE id = 144)
GROUP BY p.id, p.name, p.status, p.country_id;

-- 3. Get all branches with their cities and services
SELECT 
    p.name as provider_name,
    pb.name as branch_name,
    pb.priority,
    pb.email,
    GROUP_CONCAT(DISTINCT c.name) as cities,
    GROUP_CONCAT(DISTINCT st.name) as services
FROM provider_branches pb
INNER JOIN providers p ON pb.provider_id = p.id
LEFT JOIN branch_cities bc ON pb.id = bc.provider_branch_id
LEFT JOIN cities c ON bc.city_id = c.id
LEFT JOIN branch_services bs ON pb.id = bs.provider_branch_id
LEFT JOIN service_types st ON bs.service_type_id = st.id
WHERE p.country_id = (SELECT country_id FROM files WHERE id = 144)
GROUP BY p.id, pb.id, p.name, pb.name, pb.priority, pb.email;

-- 4. Test exact filter combination
SELECT 
    p.name as provider_name,
    pb.name as branch_name,
    pb.priority,
    pb.email
FROM provider_branches pb
INNER JOIN providers p ON pb.provider_id = p.id
INNER JOIN branch_cities bc ON pb.id = bc.provider_branch_id
INNER JOIN branch_services bs ON pb.id = bs.provider_branch_id
WHERE p.country_id = (SELECT country_id FROM files WHERE id = 144)
  AND bc.city_id = (SELECT city_id FROM files WHERE id = 144)
  AND bs.service_type_id = (SELECT service_type_id FROM files WHERE id = 144)
  AND bs.is_active = 1
ORDER BY pb.priority;

-- 5. Test individual filters
-- Country filter only
SELECT COUNT(*) as country_filter_results
FROM provider_branches pb
INNER JOIN providers p ON pb.provider_id = p.id
WHERE p.country_id = (SELECT country_id FROM files WHERE id = 144);

-- Service type filter only
SELECT COUNT(*) as service_filter_results
FROM provider_branches pb
INNER JOIN branch_services bs ON pb.id = bs.provider_branch_id
WHERE bs.service_type_id = (SELECT service_type_id FROM files WHERE id = 144)
  AND bs.is_active = 1;

-- City filter only
SELECT COUNT(*) as city_filter_results
FROM provider_branches pb
INNER JOIN branch_cities bc ON pb.id = bc.provider_branch_id
WHERE bc.city_id = (SELECT city_id FROM files WHERE id = 144);
