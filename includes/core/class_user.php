<?php

class User {

    // GENERAL

    public static function user_info($d) {
        // vars
        if(!is_array($d)){
            $user_id = (int) $d;
        }else{
            $user_id = (int) $d['user_id'];
        }
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if (!is_null($user_id)) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return [
            'id' => 0,
            'access' => 0,
            'plot_id' => '',
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
            'email' => '',
            'last_login' => ''
        ];
        // info
        $q = DB::query("SELECT user_id, access, plot_id, first_name, last_name, email, phone, last_login FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'access' => (int) $row['access'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => phone_formatting($row['phone']),
                'last_login' =>  time() - (int) $row['last_login'] < 25*365*24*60*60 ? date("d-m-Y", (int) $row['last_login']) : 'long time ago or never'
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0,
                'plot_id' => '',
                'first_name' => '',
                'last_name' => '',
                'phone' => '',
                'email' => '',
                'last_login' => ''
            ];
        }        
        
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    //
    public static function users_list($d = []) {
        // vars
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];
        // where
        $where = [];
        if ($search) {
            $where[] = "phone LIKE '%".$search."%'";
            $where[] = "email LIKE '%".$search."%'";
            $where[] = "first_name LIKE '%".$search."%'";
            $where[] = "last_name LIKE '%".$search."%'";            
        }
        $where = $where ? "WHERE ".implode(" OR ", $where) : "";
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, email, phone, last_login
            FROM users ".$where." ORDER BY user_id LIMIT ".$offset.", ".$limit.";") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => phone_formatting($row['phone']),
                'last_login' => time() - (int) $row['last_login'] < 25*365*24*60*60 ? date("d-m-Y", (int) $row['last_login']) : 'long time ago or never'
            ];
        }
        // paginator
        $q = DB::query("SELECT count(*) FROM users ".$where.";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search='.$search;
        paginator($count, $offset, $limit, $url, $paginator);
        // output
        return ['items' => $items, 'paginator' => $paginator];
    }
    
    public static function users_fetch($d = []) {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    // ACTIONS

    public static function user_edit_window($d = []) {
        
        $user_id = (int) $d['user_id'];
        HTML::assign('user', User::user_info($user_id));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = []) {
        
        // vars
        //
        
        $user_id = array_key_exists('user_id',$d) ? (int) $d['user_id'] : 0;
        $plot_id = array_key_exists('plot_id',$d) ? htmlspecialchars((string) $d['plot_id']) : '';
        $first_name = array_key_exists('first_name',$d) ? htmlspecialchars((string) $d['first_name']) : '';
        $last_name = array_key_exists('last_name',$d) ? htmlspecialchars((string) $d['last_name']) : '';
        $phone = array_key_exists('phone',$d) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        $email = array_key_exists('email',$d) ? strtolower((string) htmlspecialchars($d['email'])) : '';

        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;    

        // update
        if ($user_id) {
            $set = [];

            $set[] = "plot_id='".$plot_id."'";
            $set[] = "first_name='".$first_name."'";
            $set[] = "last_name='".$last_name."'";
            $set[] = "phone='".$phone."'";
            $set[] = "email='".$email."'";

            $set = implode(", ", $set);
            DB::query("UPDATE users SET ".$set." WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
        } else {
            DB::query("INSERT INTO users (
                plot_id,
                first_name,
                last_name,
                phone,
                email
            ) VALUES (
                '".$plot_id."',
                '".$first_name."',
                '".$last_name."',
                '".$phone."',
                '".$email."'
            );") or die (DB::error());
        }
        // output
        
        return User::users_fetch(['offset' => $offset]);
       
    }

    public static function user_delete($d = []) {
        
        // vars
        //
        $user_id = array_key_exists('user_id',$d) ? (int) $d['user_id'] : 0;
        
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;    

        // update
        if ($user_id) {
            DB::query("DELETE FROM users WHERE user_id='".$user_id."'") or die (DB::error());
        }
        // output
        
        return User::users_fetch(['offset' => $offset]);
       
    }

}
