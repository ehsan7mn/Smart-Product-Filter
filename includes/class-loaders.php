<?php
defined( 'ABSPATH' ) || exit;

class SPF_Loaders {

    /**
     * @return array<int, array{label: string, has_text: bool}>
     */
    public static function get_all() {
        return [
            1  => [ 'label' => 'متن تایپ‌شونده',           'has_text' => true ],
            2  => [ 'label' => 'متن اسلایدی',              'has_text' => true ],
            3  => [ 'label' => 'نوار پیشرفت',              'has_text' => false ],
            4  => [ 'label' => 'متن پرشونده',              'has_text' => true ],
            5  => [ 'label' => 'موج دایره‌ای',             'has_text' => false ],
            6  => [ 'label' => 'توپ پرش',                  'has_text' => false ],
            7  => [ 'label' => 'دو نقطه محو',              'has_text' => false ],
            8  => [ 'label' => 'سه نقطه رنگی',             'has_text' => false ],
            9  => [ 'label' => 'ماسک گوشه‌ای',             'has_text' => false ],
            10 => [ 'label' => 'سه نقطه اسلایدی',          'has_text' => false ],
        ];
    }

    public static function get_labels() {
        $labels = [];
        foreach ( self::get_all() as $id => $loader ) {
            $labels[ $id ] = $loader['label'];
        }
        return $labels;
    }

    public static function is_valid( $id ) {
        return isset( self::get_all()[ (int) $id ] );
    }

    public static function sanitize( $id ) {
        $id = absint( $id );
        return self::is_valid( $id ) ? $id : 1;
    }
}
