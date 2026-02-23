<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Pola Flavor Commerce na stronie profilu użytkownika w WP Admin
 */
class FC_User_Profile {

    public static function init() {
        add_action( 'show_user_profile', array( __CLASS__, 'render_fields' ) );
        add_action( 'edit_user_profile', array( __CLASS__, 'render_fields' ) );
        add_action( 'personal_options_update', array( __CLASS__, 'save_fields' ) );
        add_action( 'edit_user_profile_update', array( __CLASS__, 'save_fields' ) );
    }

    /**
     * Wyświetl pola FC w profilu użytkownika
     */
    public static function render_fields( $user ) {
        if ( ! current_user_can( 'edit_users' ) && ! current_user_can( 'edit_user', $user->ID ) ) {
            return;
        }

        $account_type = get_user_meta( $user->ID, 'fc_account_type', true ) ?: 'private';

        // Billing
        $b = array(
            'first_name' => get_user_meta( $user->ID, 'fc_billing_first_name', true ),
            'last_name'  => get_user_meta( $user->ID, 'fc_billing_last_name', true ),
            'company'    => get_user_meta( $user->ID, 'fc_billing_company', true ),
            'tax_no'     => get_user_meta( $user->ID, 'fc_billing_tax_no', true ),
            'crn'        => get_user_meta( $user->ID, 'fc_billing_crn', true ),
            'address'    => get_user_meta( $user->ID, 'fc_billing_address', true ),
            'postcode'   => get_user_meta( $user->ID, 'fc_billing_postcode', true ),
            'city'       => get_user_meta( $user->ID, 'fc_billing_city', true ),
            'country'    => get_user_meta( $user->ID, 'fc_billing_country', true ),
            'phone'        => get_user_meta( $user->ID, 'fc_billing_phone', true ),
            'phone_prefix' => get_user_meta( $user->ID, 'fc_billing_phone_prefix', true ),
        );

        // Shipping
        $ship_different = get_user_meta( $user->ID, 'fc_ship_to_different', true );
        $s = array(
            'first_name' => get_user_meta( $user->ID, 'fc_shipping_first_name', true ),
            'last_name'  => get_user_meta( $user->ID, 'fc_shipping_last_name', true ),
            'company'    => get_user_meta( $user->ID, 'fc_shipping_company', true ),
            'address'    => get_user_meta( $user->ID, 'fc_shipping_address', true ),
            'postcode'   => get_user_meta( $user->ID, 'fc_shipping_postcode', true ),
            'city'       => get_user_meta( $user->ID, 'fc_shipping_city', true ),
            'country'    => get_user_meta( $user->ID, 'fc_shipping_country', true ),
        );
        ?>

        <h2><?php fc_e( 'profile_flavor_commerce_customer_data' ); ?></h2>
        <?php wp_nonce_field( 'fc_save_user_profile_' . $user->ID, 'fc_user_profile_nonce' ); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th><label for="fc_account_type"><?php fc_e( 'profile_account_type' ); ?></label></th>
                <td>
                    <select name="fc_account_type" id="fc_account_type">
                        <option value="private" <?php selected( $account_type, 'private' ); ?>><?php fc_e( 'profile_individual' ); ?></option>
                        <option value="company" <?php selected( $account_type, 'company' ); ?>><?php fc_e( 'profile_company' ); ?></option>
                    </select>
                </td>
            </tr>
        </table>

        <h3><?php fc_e( 'profile_billing_address' ); ?></h3>
        <table class="form-table" role="presentation">
            <tr class="fc-private-row">
                <th><label for="fc_billing_first_name"><?php fc_e( 'profile_first_name' ); ?></label></th>
                <td><input type="text" name="fc_billing_first_name" id="fc_billing_first_name" value="<?php echo esc_attr( $b['first_name'] ); ?>" class="regular-text"></td>
            </tr>
            <tr class="fc-private-row">
                <th><label for="fc_billing_last_name"><?php fc_e( 'profile_last_name' ); ?></label></th>
                <td><input type="text" name="fc_billing_last_name" id="fc_billing_last_name" value="<?php echo esc_attr( $b['last_name'] ); ?>" class="regular-text"></td>
            </tr>
            <tr class="fc-company-row">
                <th><label for="fc_billing_company"><?php fc_e( 'profile_company' ); ?></label></th>
                <td><input type="text" name="fc_billing_company" id="fc_billing_company" value="<?php echo esc_attr( $b['company'] ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="fc_billing_country"><?php fc_e( 'set_country' ); ?></label></th>
                <td>
                    <?php
                    $countries = FC_Shortcodes::get_allowed_countries( 'admin' );
                    $sel_country = $b['country'] ?: 'PL';
                    FC_Shortcodes::render_admin_country_field( 'fc_billing_country', $sel_country, 'fc_billing_country', $countries );
                    ?>
                </td>
            </tr>
            <tr class="fc-company-row">
                <th><label for="fc_billing_tax_no" id="fc_billing_tax_no_label"><?php fc_e( 'set_tax_id' ); ?></label></th>
                <td><input type="text" name="fc_billing_tax_no" id="fc_billing_tax_no" value="<?php echo esc_attr( $b['tax_no'] ); ?>" class="regular-text"></td>
            </tr>
            <tr class="fc-company-row">
                <th><label for="fc_billing_crn" id="fc_billing_crn_label"><?php fc_e( 'set_company_registration_number' ); ?></label></th>
                <td><input type="text" name="fc_billing_crn" id="fc_billing_crn" value="<?php echo esc_attr( $b['crn'] ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="fc_billing_address"><?php fc_e( 'profile_address' ); ?></label></th>
                <td><input type="text" name="fc_billing_address" id="fc_billing_address" value="<?php echo esc_attr( $b['address'] ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="fc_billing_postcode"><?php fc_e( 'set_postal_code_city' ); ?></label></th>
                <td>
                    <input type="text" name="fc_billing_postcode" id="fc_billing_postcode" value="<?php echo esc_attr( $b['postcode'] ); ?>" style="width:120px;vertical-align:middle;">
                    <input type="text" name="fc_billing_city" id="fc_billing_city" value="<?php echo esc_attr( $b['city'] ); ?>" class="regular-text" style="width:calc(25em - 130px);vertical-align:middle;">
                </td>
            </tr>
            <tr>
                <th><label for="fc_billing_phone"><?php fc_e( 'set_phone' ); ?></label></th>
                <td>
                    <?php FC_Shortcodes::render_admin_phone_field( 'fc_billing_phone', 'fc_billing_phone_prefix', $b['phone'], $b['phone_prefix'], 'fc_billing_phone' ); ?>
                </td>
            </tr>
        </table>

        <h3><?php fc_e( 'profile_shipping_address' ); ?></h3>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="fc_ship_to_different"><?php fc_e( 'profile_different_shipping_address' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="fc_ship_to_different" id="fc_ship_to_different" value="1" <?php checked( $ship_different, '1' ); ?>>
                        <?php fc_e( 'profile_ship_to_a_different_address_than_billing' ); ?>
                    </label>
                </td>
            </tr>
            <tr class="fc-shipping-field fc-private-row">
                <th><label for="fc_shipping_first_name"><?php fc_e( 'profile_first_name' ); ?></label></th>
                <td><input type="text" name="fc_shipping_first_name" id="fc_shipping_first_name" value="<?php echo esc_attr( $s['first_name'] ); ?>" class="regular-text"></td>
            </tr>
            <tr class="fc-shipping-field fc-private-row">
                <th><label for="fc_shipping_last_name"><?php fc_e( 'profile_last_name' ); ?></label></th>
                <td><input type="text" name="fc_shipping_last_name" id="fc_shipping_last_name" value="<?php echo esc_attr( $s['last_name'] ); ?>" class="regular-text"></td>
            </tr>
            <tr class="fc-shipping-field fc-company-row">
                <th><label for="fc_shipping_company"><?php fc_e( 'profile_company' ); ?></label></th>
                <td><input type="text" name="fc_shipping_company" id="fc_shipping_company" value="<?php echo esc_attr( $s['company'] ); ?>" class="regular-text"></td>
            </tr>
            <tr class="fc-shipping-field">
                <th><label for="fc_shipping_address"><?php fc_e( 'profile_address' ); ?></label></th>
                <td><input type="text" name="fc_shipping_address" id="fc_shipping_address" value="<?php echo esc_attr( $s['address'] ); ?>" class="regular-text"></td>
            </tr>
            <tr class="fc-shipping-field">
                <th><label for="fc_shipping_postcode"><?php fc_e( 'set_postal_code_city' ); ?></label></th>
                <td>
                    <input type="text" name="fc_shipping_postcode" id="fc_shipping_postcode" value="<?php echo esc_attr( $s['postcode'] ); ?>" style="width:120px;vertical-align:middle;">
                    <input type="text" name="fc_shipping_city" id="fc_shipping_city" value="<?php echo esc_attr( $s['city'] ); ?>" class="regular-text" style="width:calc(25em - 130px);vertical-align:middle;">
                </td>
            </tr>
            <tr class="fc-shipping-field">
                <th><label for="fc_shipping_country"><?php fc_e( 'set_country' ); ?></label></th>
                <td>
                    <?php
                    $s_sel_country = $s['country'] ?: 'PL';
                    FC_Shortcodes::render_admin_country_field( 'fc_shipping_country', $s_sel_country, 'fc_shipping_country', $countries );
                    ?>
                </td>
            </tr>
        </table>

        <script>
        jQuery(function($){
            var countryLabels = {
                AL:{tax_no:"NIPT",reg:"Numri i Regjistrimit (QKR)"},
                AT:{tax_no:"UID (ATU)",reg:"Firmenbuchnummer (FN)"},
                BY:{tax_no:"УНП",reg:"Рэгістрацыйны нумар"},
                BE:{tax_no:"BTW / TVA",reg:"Ondernemingsnummer (KBO)"},
                BA:{tax_no:"PDV broj",reg:"Registarski broj"},
                BG:{tax_no:"ИН по ДДС",reg:"ЕИК (Булстат)"},
                HR:{tax_no:"OIB",reg:"Matični broj subjekta (MBS)"},
                CY:{tax_no:"Αριθμός ΦΠΑ",reg:"Αριθμός Εγγραφής (HE)"},
                ME:{tax_no:"PIB",reg:"Registarski broj"},
                CZ:{tax_no:"DIČ",reg:"Identifikační číslo (IČO)"},
                DK:{tax_no:"SE-nummer",reg:"CVR-nummer"},
                EE:{tax_no:"KMKR number",reg:"Registrikood"},
                FI:{tax_no:"ALV-numero",reg:"Y-tunnus"},
                FR:{tax_no:"Numéro de TVA",reg:"Numéro SIREN / SIRET"},
                GR:{tax_no:"ΑΦΜ",reg:"Αριθμός ΓΕΜΗ"},
                ES:{tax_no:"NIF / CIF",reg:"Registro Mercantil"},
                NL:{tax_no:"BTW-nummer",reg:"KVK-nummer"},
                IE:{tax_no:"VAT Number",reg:"Company Registration (CRO)"},
                IS:{tax_no:"Virðisaukaskattnúmer (VSK)",reg:"Kennitala"},
                LT:{tax_no:"PVM mokėtojo kodas",reg:"Įmonės kodas"},
                LU:{tax_no:"Numéro TVA",reg:"Numéro RCS"},
                LV:{tax_no:"PVN numurs",reg:"Reģistrācijas Nr."},
                MK:{tax_no:"ДДВ број",reg:"ЕМБС"},
                MT:{tax_no:"VAT Number",reg:"Company Number (C)"},
                MD:{tax_no:"Codul TVA",reg:"IDNO (Cod fiscal)"},
                DE:{tax_no:"Umsatzsteuer-IdNr.",reg:"Handelsregisternummer (HRB)"},
                NO:{tax_no:"MVA-nummer",reg:"Organisasjonsnummer"},
                PL:{tax_no:"NIP",reg:"KRS / REGON"},
                PT:{tax_no:"Número de contribuinte (NIF)",reg:"NIPC"},
                RO:{tax_no:"Cod de identificare fiscală (CIF)",reg:"Nr. Registrul Comerțului"},
                RS:{tax_no:"ПИБ",reg:"Матични број"},
                SK:{tax_no:"IČ DPH",reg:"Identifikačné číslo (IČO)"},
                SI:{tax_no:"Identifikacijska št. za DDV",reg:"Matična številka"},
                CH:{tax_no:"MWST-Nr. / Numéro TVA",reg:"Unternehmens-Id. (CHE/UID)"},
                SE:{tax_no:"Momsregistreringsnummer",reg:"Organisationsnummer"},
                UA:{tax_no:"ІПН",reg:"Код ЄДРПОУ"},
                HU:{tax_no:"Adószám",reg:"Cégjegyzékszám"},
                GB:{tax_no:"VAT Registration Number",reg:"Company Registration Number"},
                IT:{tax_no:"Partita IVA",reg:"Numero REA"}
            };
            function fcUpdateUserLabels(code){
                var d = countryLabels[code] || {tax_no:"NIP",reg:"Nr rejestrowy firmy"};
                $('#fc_billing_tax_no_label').text(d.tax_no);
                $('#fc_billing_crn_label').text(d.reg);
            }
            var userCountryPrefixes = {
                AL:'+355',AT:'+43',BY:'+375',BE:'+32',BA:'+387',BG:'+359',HR:'+385',CY:'+357',
                ME:'+382',CZ:'+420',DK:'+45',EE:'+372',FI:'+358',FR:'+33',GR:'+30',ES:'+34',
                NL:'+31',IE:'+353',IS:'+354',LT:'+370',LU:'+352',LV:'+371',MK:'+389',MT:'+356',
                MD:'+373',DE:'+49',NO:'+47',PL:'+48',PT:'+351',RO:'+40',RS:'+381',SK:'+421',
                SI:'+386',CH:'+41',SE:'+46',UA:'+380',HU:'+36',GB:'+44',IT:'+39'
            };
            $('#fc_billing_country').on('change',function(){
                var code = $(this).val();
                fcUpdateUserLabels(code);
                var prefix = userCountryPrefixes[code];
                if(prefix && typeof fcAdminSetPhonePrefix==='function'){
                    fcAdminSetPhonePrefix('fc-admin-phone-fc_billing_phone_prefix',code,prefix);
                }
            });
            fcUpdateUserLabels($('#fc_billing_country').val());

            function fcToggleCompanyRows(type){
                if(type==='company'){
                    $('.fc-company-row').show();
                    $('.fc-private-row').hide();
                } else {
                    $('.fc-company-row').hide();
                    $('.fc-private-row').show();
                }
            }
            $('#fc_account_type').on('change',function(){
                fcToggleCompanyRows($(this).val());
                fcToggleShippingFields();
            });
            fcToggleCompanyRows($('#fc_account_type').val());

            function fcToggleShippingFields(){
                var checked = $('#fc_ship_to_different').is(':checked');
                if(checked){
                    $('.fc-shipping-field').show();
                    fcToggleCompanyRows($('#fc_account_type').val());
                } else {
                    $('.fc-shipping-field').hide();
                }
            }
            $('#fc_ship_to_different').on('change', fcToggleShippingFields);
            fcToggleShippingFields();
        });
        </script>
        <?php
    }

    /**
     * Zapisz pola FC
     */
    public static function save_fields( $user_id ) {
        if ( ! current_user_can( 'edit_users' ) && ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_POST['fc_user_profile_nonce'] ) || ! wp_verify_nonce( $_POST['fc_user_profile_nonce'], 'fc_save_user_profile_' . $user_id ) ) {
            return;
        }

        // Typ konta
        update_user_meta( $user_id, 'fc_account_type', sanitize_text_field( $_POST['fc_account_type'] ?? 'private' ) );

        // Billing
        $billing_fields = array(
            'fc_billing_first_name',
            'fc_billing_last_name',
            'fc_billing_company',
            'fc_billing_tax_no',
            'fc_billing_crn',
            'fc_billing_address',
            'fc_billing_postcode',
            'fc_billing_city',
            'fc_billing_country',
            'fc_billing_phone',
            'fc_billing_phone_prefix',
        );
        foreach ( $billing_fields as $field ) {
            update_user_meta( $user_id, $field, sanitize_text_field( $_POST[ $field ] ?? '' ) );
        }

        // Shipping
        update_user_meta( $user_id, 'fc_ship_to_different', isset( $_POST['fc_ship_to_different'] ) ? '1' : '0' );

        $shipping_fields = array(
            'fc_shipping_first_name',
            'fc_shipping_last_name',
            'fc_shipping_company',
            'fc_shipping_address',
            'fc_shipping_postcode',
            'fc_shipping_city',
            'fc_shipping_country',
        );
        foreach ( $shipping_fields as $field ) {
            update_user_meta( $user_id, $field, sanitize_text_field( $_POST[ $field ] ?? '' ) );
        }
    }
}
