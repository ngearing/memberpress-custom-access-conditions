<?php
/**
 * Plugin Name: MemberPress Group Rules
 * Description: This plugin add Group Rules to MemberPress's Access Conditions.
 *
 * @package mpgr
 */

class FilterableScripts extends Wp_Scripts {
	public function localize( $handle, $object_name, $l10n ) {
		$l10n = apply_filters( 'script_l10n', $l10n, $handle, $object_name );
		return parent::localize( $handle, $object_name, $l10n );
	}
}

add_action(
	'init',
	function() {
		if ( ! is_admin() ) {
			return;
		}

		$GLOBALS['wp_scripts'] = new FilterableScripts(); // WPCS: override ok.
	},
	0
);

add_filter(
	'mepr_view_paths_get_string',
	function( $paths, $slug, $vars ) {

		$views = [
			'/admin/rules/form',
			'/admin/rules/access_row',
		];

		if ( in_array( $slug, $views ) ) {
			$paths[] = __DIR__ . '/templates/';
		}

		return $paths;
	},
	10,
	3
);

class MeprCustomRule extends MeprRule {
	public static function mepr_access_types() {
		return apply_filters( 'mepr_get_access_types', parent::mepr_access_types() );
	}
}

class MeprCustomRulesHelper extends MeprRulesHelper {

	public static function access_types_dropdown( $selected = '', $onchange = 'mepr_show_access_options(this)' ) {
		// Use custom class to add custom types.
		$access_types = MeprCustomRule::mepr_access_types();
		?>
		<select name="mepr_access_row[type][]" class="mepr-rule-access-type-input" onchange="<?php echo $onchange; ?>" data-validation="required" data-validation-error-msg="<?php _e( 'Rule Type cannot be blank', 'memberpress' ); ?>">
			<option value=""><?php _e( '- Select Type -', 'memberpress' ); ?></option>
			<?php foreach ( $access_types as $type ) : ?>
			<option value="<?php echo $type['value']; ?>" <?php echo $selected == $type['value'] ? 'selected' : ''; ?>>
				<?php echo $type['label']; ?>
			</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function access_operators_dropdown_string( $type = '', $selected = '' ) {
		ob_start();
		parent::access_operators_dropdown( $type, $selected );
		$html = ob_get_clean();
		$html = apply_filters( 'mepr_access_operators_dropdown', $html, $type, $selected );
		return $html;
	}

	public static function access_conditions_dropdown_string( $type = '', $selected = '' ) {
		ob_start();
		parent::access_conditions_dropdown( $type, $selected );
		$html = ob_get_clean();
		$html = apply_filters( 'mepr_access_conditions_dropdown', $html, $type, $selected );
		return $html;
	}
}

abstract class MeprCustomAccessCondition {

	const TYPE  = '';
	const LABEL = '';

	public function __construct() {

		// Add access type to rule.
		add_filter( 'mepr_get_access_types', [ $this, 'add_access_type' ], 10, 1 );

		// Add stuff to localized js.
		add_filter( 'script_l10n', [ $this, 'setup_admin_access_row_js' ], 10, 3 );

		// Display admin access conditions.
		add_filter( 'mepr_access_operators_dropdown', [ $this, 'access_operators_dropdown' ], 10, 3 );
		add_filter( 'mepr_access_conditions_dropdown', [ $this, 'access_conditions_dropdown' ], 10, 3 );

		// Show or hide content.
		add_filter( 'mepr-pre-run-rule-redirection', [ $this, 'pre_run_rule_redirection' ], 10, 3 );
		add_filter( 'mepr-pre-run-rule-content', [ $this, 'pre_run_rule_content' ], 10, 3 );
	}

	abstract function access_condition( $hide, $condition );

	abstract function access_operators_dropdown( $html, $type, $selected );

	abstract function access_conditions_dropdown( $html, $type, $selected );

	public function add_access_type( $types ) {
		return array_merge(
			$types,
			[
				[
					'value' => static::TYPE,
					'label' => __( static::LABEL, 'meprcustom' ),
				],
			]
		);
	}

	public function setup_admin_access_row_js( $l10n, $handle, $object_name ) {

		if ( $handle == 'mepr-rules-js' ) {
			$l10n['access_row'][ static::TYPE ] = [
				'row_tpl'       => MeprCustomRulesHelper::access_row_string( new MeprRuleAccessCondition( array( 'access_type' => static::TYPE ) ), 1 ),
				'types_tpl'     => MeprCustomRulesHelper::access_types_dropdown_string( static::TYPE ),
				'operator_tpl'  => MeprCustomRulesHelper::access_operators_dropdown_string( static::TYPE ),
				'condition_tpl' => MeprCustomRulesHelper::access_conditions_dropdown_string( static::TYPE ),
			];
		}

		return $l10n;
	}

	public function pre_run_rule_redirection( $hide, $uri, $delim ) {

		$access = MeprRule::get_access_list( get_page_by_path( $uri ) );

		if ( in_array( static::TYPE, array_keys( $access ), true ) ) {
			return $this->access_condition( $hide, $access[ static::TYPE ] );
		}

		return $hide;
	}

	public function pre_run_rule_content( $hide, $post, $uri ) {

		$access = MeprRule::get_access_list( $post );

		if ( in_array( static::TYPE, array_keys( $access ), true ) ) {
			return $this->access_condition( $hide, $access[ static::TYPE ] );
		}

		return $hide;
	}
}

class GroupAccessCondition extends MeprCustomAccessCondition {
	const TYPE  = 'group';
	const LABEL = 'Group';

	public function access_condition( $hide, $condition ) {
		$user = wp_get_current_user();
		if ( ! $user ) {
			return true; // Hide content.
		}

		$group_user = new Groups_User( $user->ID );

		foreach ( $condition as $group_id ) {
			if ( $group_user->is_member( $group_id ) ) {
				return false; // Show content.
			}
		}

		return $hide;
	}

	public function access_operators_dropdown( $html, $type, $selected ) {
		if ( $type == self::TYPE ) {
			// Use same as member operators.
			$type = 'member';
		}

		$html = MeprRulesHelper::access_operators_dropdown_string( $type, $selected );

		return $html;
	}

	public function access_conditions_dropdown( $html, $type = '', $selected = '' ) {

		if ( $type == self::TYPE ) {
			$groups  = \Groups_Group::get_groups();
			$options = array_map(
				function( $o ) {
					return sprintf(
						'<option value="%s">%s</option>',
						$o->group_id,
						$o->name
					);
				},
				$groups
			);

			$html = sprintf(
				'<select name="mepr_access_row[condition][]" class="mepr-rule-access-condition-input">%s</select>',
				implode( $options )
			);
		}

		return $html;
	}
}
new GroupAccessCondition();

