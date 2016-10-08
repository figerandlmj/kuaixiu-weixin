<?php

namespace ZM\MemberBundle\Controller;

class Pagination {

    public $total = 0;
    public $page = 1;
    public $limit = 20;
    public $num_links = 10;
    public $url = '';
    public $text = '共{total}条记录，第{page}/{pages}页';
    public $text_first = '|&lt;';
    public $text_last = '&gt;|';
    public $text_next = '&gt;';
    public $text_prev = '&lt;';
    public $style_links = 'links';
    public $style_results = 'results';

    public function render() {
        $total = $this->total;

        if ($this->page < 1) {
            $page = 1;
        } else {
            $page = $this->page;
        }

        if (!(int) $this->limit) {
            $limit = 15;
        } else {
            $limit = $this->limit;
        }
        
        $this->url = $this->url . '&limit=' . $limit;

        $num_links = $this->num_links;
        $num_pages = ceil($total / $limit);

        $output = '';

        if ($page > 1) {
            $output .= ' <a href="' . str_replace('{page}', 1, $this->url) . '">' . $this->text_first . '</a> <a href="' . str_replace('{page}', $page - 1, $this->url) . '">' . $this->text_prev . '</a> ';
        }

        if ($num_pages > 1) {
            if ($num_pages <= $num_links) {
                $start = 1;
                $end = $num_pages;
            } else {
                $start = $page - floor($num_links / 2);
                $end = $page + floor($num_links / 2);

                if ($start < 1) {
                    $end += abs($start) + 1;
                    $start = 1;
                }

                if ($end > $num_pages) {
                    $start -= ($end - $num_pages);
                    $end = $num_pages;
                }
            }

//            if ($start > 1) {
//                $output .= ' <a href="#">...</a> ';
//            }

            for ($i = $start; $i <= $end; $i++) {
                if ($page == $i) {
                    $output .= ' <b>' . $i . '</b> ';
                }
//                else {
//                    $output .= ' <a href="' . str_replace('{page}', $i, $this->url) . '">' . $i . '</a> ';
//                }
            }

//            if ($end < $num_pages) {
//                $output .= ' <a href="#">...</a> ';
//            }
        }

        if ($page < $num_pages) {
            $output .= ' <a href="' . str_replace('{page}', $page + 1, $this->url) . '">' . $this->text_next . '</a> <a href="' . str_replace('{page}', $num_pages, $this->url) . '">' . $this->text_last . '</a> ';
        }

        $find = array(
            '{total}',
            '{page}',
            '{pages}'
        );

        $replace = array(
            $total,
            $page,
            $num_pages
        );
        
//        $output = '<span>' . str_replace($find, $replace, $this->text) . '</span>' . $output;
        
        return '<div class="' . $this->style_links . '">' . $output . '</div>';
    }

}

?>