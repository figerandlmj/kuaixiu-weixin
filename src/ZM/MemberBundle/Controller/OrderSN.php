<?php

namespace ZM\MemberBundle\Controller;

class OrderSN {
    
    public function make_order($user_id)
    {
            return mt_rand(10,99)
          . sprintf('%010d',time() - 946656000)
          . sprintf('%03d', (float) microtime() * 1000)
          . sprintf('%03d', (int) $user_id % 1000);
    }
}
