<?php
// Prevent direct access
defined('ABSPATH') or die('No script please!');

class Booking_Form_Widget extends WP_Widget {
    function __construct() {
        parent::__construct(
            'booking_form_widget',
            __('Booking Form Widget', 'text_domain'),
            array('description' => __('A widget to display the booking form', 'text_domain'))
        );
    }

    public function widget($args, $instance) {
        $niche = !empty($instance['niche']) ? $instance['niche'] : 'hotel';

        echo $args['before_widget'];
        echo booking_form_shortcode(array('niche' => $niche));
        echo $args['after_widget'];
    }

    public function form($instance) {
        $niche = !empty($instance['niche']) ? $instance['niche'] : __('hotel', 'text_domain');
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('niche'); ?>"><?php _e('Niche:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('niche'); ?>" name="<?php echo $this->get_field_name('niche'); ?>" type="text" value="<?php echo esc_attr($niche); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['niche'] = (!empty($new_instance['niche'])) ? strip_tags($new_instance['niche']) : '';

        return $instance;
    }
}

function register_booking_form_widget() {
    register_widget('Booking_Form_Widget');
}
add_action('widgets_init', 'register_booking_form_widget');
