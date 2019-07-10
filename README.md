# MemberPress Custom Access Conditions

This is a WordPress plugin that extends the functionality of the [MemberPress](https://memberpress.com/) plugin to make it easier to add your own custom access conditions to protected content.

## How to use

Extend the abstract class `MeprCustomAccessCondition` and add methods for `access_condition`, `access_operators_dropdown` and `access_conditions_dropdown` functions.

## Example

Here is an example where we are providing access conditions for the [Groups](https://wordpress.org/plugins/groups/) plugin.

```php
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
```

## Authors

-   **Nathan Gearing** - [ngearing](https://github.com/ngearing)

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details
