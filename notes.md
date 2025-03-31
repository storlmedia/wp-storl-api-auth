### This Plugin Requires the PHP GMP Extension
```
sudo apt install php-gmp
```


### Insert User Mapping
```SQL
INSERT INTO
    wp_storl_user_mappings (user_id, external_user_id)
VALUES
    (1, 'cc793e95-3df9-4e7d-aa72-7084ea226638');
```

### Delete Transient
```SQL
DELETE FROM wp_options where option_name LIKE '%_storl_auth_jwks%';
```

### List external user ids from daggerhart-openid-connect-generic plugin

```SQL
SELECT
    ID user_id,
    MAX(meta_value) external_user_id
FROM
    wp_users u
INNER JOIN
    wp_usermeta um on u.ID = um.user_id
WHERE
    um.meta_key = 'openid-connect-generic-subject-identity'
GROUP BY
    u.ID
LIMIT 10
;
```

### Migrate external user id mapping from daggerhart-openid-connect-generic

```SQL
INSERT INTO wp_storl_user_mappings (user_id, external_user_id)
    (
        SELECT
            ID user_id,
            MAX(meta_value) external_user_id
        FROM
            wp_users u
        INNER JOIN
            wp_usermeta um on u.ID = um.user_id
        WHERE
            um.meta_key = 'openid-connect-generic-subject-identity'
        GROUP BY
            u.ID
    )
ON DUPLICATE KEY UPDATE
    external_user_id = VALUES(external_user_id);
```