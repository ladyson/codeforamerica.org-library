<?php

    class Context
    {
        // PDO handle to SQLite DB.
        var $dbh;
    
        function __construct($dbname)
        {
            $this->dbh = new PDO("sqlite:{$dbname}");
        }
        
       /**
        * Run a query, fetch results list as associative arrays, and return.
        */
        function select($q)
        {
            return $this->dbh->query($q, PDO::FETCH_ASSOC);
        }
        
       /**
        * Call select() with the same parameters as sprintf: format + args list.
        */
        function selectf($format)
        {
            $args = array_map(array($this->dbh, 'quote'), func_get_args());
            $args[0] = $format; // don't quote the format string
            $query = call_user_func_array('sprintf', $args);

            return $this->select($query);
        }
        
        function path_info()
        {
            return urldecode(ltrim($_SERVER['PATH_INFO'], '/'));
        }
        
        function base()
        {
            return rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        }
    }
    
    function html($string)
    {
        return htmlspecialchars($string);
    }
    
    function enc($string)
    {
        return urlencode($string);
    }
    
    function item_href(&$ctx, $item)
    {
        $name = $item['slug'] ? $item['slug'] : $item['id'];
        return $ctx->base() . '/item/' . urlencode($name);
    }
    
    function category_href(&$ctx, $category)
    {
        return $ctx->base() . '/category/' . urlencode($category);
    }
    
    function tag_href(&$ctx, $tag)
    {
        return $ctx->base() . '/tag/' . urlencode($tag);
    }
    
    function program_href(&$ctx, $program)
    {
        return $ctx->base() . '/program/' . urlencode($program);
    }
    
    function location_href(&$ctx, $location)
    {
        return $ctx->base() . '/location/' . urlencode($location);
    }
    
    function person_href(&$ctx, $person)
    {
        return '#'; //$ctx->base() . '/person/' . urlencode($person['name']);
    }
    
    function item_anchor(&$ctx, $item)
    {
        $href = item_href($ctx, $item);
        $html = sprintf('<a href="%s">%s</a>', html($href), html($item['title']));
        
        $extra = array();
        
        if($item['format'])
            $extra[] = $item['format'];
        
        if($item['date'])
            $extra[] = $item['date'];
        
        if($extra)
            $html .= ' ('.implode(', ', $extra).')';
        
        return $html;
    }
    
    function category_anchor(&$ctx, $category)
    {
        $href = category_href($ctx, $category['category']);
        $html = sprintf('<a href="%s">%s</a>', html($href), html($category['category']));
        
        if(is_numeric($category['items']))
            $html .= " ({$category['items']})";
        
        return $html;
    }
    
    function tag_anchor(&$ctx, $tag)
    {
        $href = tag_href($ctx, $tag['tag']);
        $html = sprintf('<a href="%s">%s</a>', html($href), html($tag['tag']));
        
        if(is_numeric($tag['items']))
            $html .= " ({$tag['items']})";
        
        return $html;
    }
    
    function program_anchor(&$ctx, $program)
    {
        $href = program_href($ctx, $program['program']);
        $html = sprintf('<a href="%s">%s</a>', html($href), html($program['program']));
        
        if(is_numeric($program['items']))
            $html .= " ({$program['items']})";
        
        return $html;
    }
    
    function location_anchor(&$ctx, $location)
    {
        $href = location_href($ctx, $location['location']);
        $html = sprintf('<a href="%s">%s</a>', html($href), html($location['location']));
        
        if(is_numeric($location['items']))
            $html .= " ({$location['items']})";
        
        return $html;
    }
    
    function embed_html($item)
    {
        if(preg_match('#^https?://(www.)?youtube.com/#', $item['link']))
        {
            if(preg_match('#\bv=(\w[\-\w]+\w)\b#', $item['link'], $m)) {
                $id = $m[1];

            } else {
                return;
            }
            
            $url = "//www.youtube.com/embed/{$id}";
            $url .= preg_match('#\blist=(\w[\-\w]+\w)\b#', $item['link'], $m)
                ? "?list={$m[1]}" : '';
            
            return "
                <div class='youtube video-embed'>
                  <div><iframe src='{$url}' frameborder='0' allowfullscreen></iframe></div>
                </div>
                ";
        }
        
        if(preg_match('#^https?://(www.)?vimeo.com(/album/\w+/video)?/(\w+)$#', $item['link'], $m))
        {
            $id = $m[3];
            $url = "//player.vimeo.com/video/{$id}?title=0&amp;byline=0&amp;portrait=0";
        
            return "
                <div class='vimeo video-embed'>
                  <div><iframe src='{$url}' frameborder='0' allowfullscreen></iframe></div>
                </div>
                ";
        }
    }
    
    function get_categories(&$ctx)
    {
        $query = 'SELECT category, COUNT(id) AS items
                  FROM items WHERE category IS NOT NULL AND category != ""
                  GROUP BY category
                  ORDER BY category';
        
        $categories = $ctx->select($query);
        
        return $categories;
    }
    
    function get_tags(&$ctx)
    {
        $query = 'SELECT tag, COUNT(item_id) AS items
                  FROM item_tags WHERE tag IS NOT NULL AND tag != ""
                  GROUP BY tag
                  ORDER BY tag';
        
        $tags = $ctx->select($query);
        
        return $tags;
    }
    
    function get_programs(&$ctx)
    {
        $query = 'SELECT program, COUNT(item_id) AS items
                  FROM item_programs WHERE program IS NOT NULL AND program != ""
                  GROUP BY program
                  ORDER BY program';
        
        $programs = $ctx->select($query);
        
        return $programs;
    }
    
    function get_locations(&$ctx)
    {
        $query = 'SELECT location, COUNT(item_id) AS items
                  FROM item_locations WHERE location IS NOT NULL AND location != ""
                  GROUP BY location
                  ORDER BY location';
        
        $locations = $ctx->select($query);
        
        return $locations;
    }
    
    function get_category_items(&$ctx, $category_name)
    {
        $query = 'SELECT * FROM items
                  WHERE category = %s
                  ORDER BY title';
        
        $items = $ctx->selectf($query, $category_name);
        
        return $items;
    }
    
    function get_tag_items(&$ctx, $tag_name)
    {
        $query = 'SELECT items.* FROM item_tags
                  LEFT JOIN items ON items.id = item_tags.item_id
                  WHERE item_tags.tag = %s
                  ORDER BY items.title';
        
        $items = $ctx->selectf($query, $tag_name);
        
        return $items;
    }
    
    function get_program_items(&$ctx, $program_name)
    {
        $query = 'SELECT items.* FROM item_programs
                  LEFT JOIN items ON items.id = item_programs.item_id
                  WHERE item_programs.program = %s
                  ORDER BY items.title';
        
        $items = $ctx->selectf($query, $program_name);
        
        return $items;
    }
    
    function get_location_items(&$ctx, $location_name)
    {
        $query = 'SELECT items.* FROM item_locations
                  LEFT JOIN items ON items.id = item_locations.item_id
                  WHERE item_locations.location = %s
                  ORDER BY items.title';
        
        $items = $ctx->selectf($query, $location_name);
        
        return $items;
    }
    
    function get_item_tags(&$ctx, $item_id)
    {
        $query = 'SELECT tag FROM item_tags
                  WHERE item_id = %s AND tag != ""
                  ORDER BY tag';
        
        $tags = $ctx->selectf($query, $item_id);
        
        return $tags;
    }
    
    function get_item_locations(&$ctx, $item_id)
    {
        $query = 'SELECT location FROM item_locations
                  WHERE item_id = %s AND location != ""
                  ORDER BY location';
        
        $locations = $ctx->selectf($query, $item_id);
        
        return $locations;
    }
    
    function get_item_programs(&$ctx, $item_id)
    {
        $query = 'SELECT program FROM item_programs
                  WHERE item_id = %s AND program != ""
                  ORDER BY program';
        
        $programs = $ctx->selectf($query, $item_id);
        
        return $programs;
    }
    
    function get_item_contacts(&$ctx, $item_id)
    {
        $query = 'SELECT people.* FROM item_contacts
                  LEFT JOIN people ON people.id = item_contacts.person_id
                  WHERE item_id = %s';
        
        
        $contacts = $ctx->selectf($query, $item_id);
        
        return $contacts;
    }
    
    function get_item_contributors(&$ctx, $item_id)
    {
        $query = 'SELECT people.* FROM item_contributors
                  LEFT JOIN people ON people.id = item_contributors.person_id
                  WHERE item_id = %s';
        
        $contributors = $ctx->selectf($query, $item_id);
        
        return $contributors;
    }
    
    function get_item(&$ctx, $name)
    {
        $query = 'SELECT * FROM items
                  WHERE id = %s OR slug = %s
                  ORDER BY CAST(slug = %s AS INT) DESC';
        
        foreach($ctx->selectf($query, $name, $name, $name) as $row)
        {
            $row['tags'] = get_item_tags($ctx, $row['id']);
            $row['locations'] = get_item_locations($ctx, $row['id']);
            $row['programs'] = get_item_programs($ctx, $row['id']);
            $row['contacts'] = get_item_contacts($ctx, $row['id']);
            $row['contributors'] = get_item_contributors($ctx, $row['id']);
        
            return $row;
        }
        
        return null;
    }

?>
