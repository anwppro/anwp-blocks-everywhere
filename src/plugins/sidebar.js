import {registerPlugin} from '@wordpress/plugins';
import {PluginDocumentSettingPanel} from '@wordpress/edit-post';
import {PanelBody, TextControl} from '@wordpress/components';
import {useSelect, useDispatch} from '@wordpress/data';

const TopMenuItemSelector = () => {

	const hookValue = useSelect( ( select ) => {
		return select( 'core/editor' ).getEditedPostAttribute( 'meta' )._anwp_be_hook;
	} );

	const priorityValue = useSelect( ( select ) => {
		return select( 'core/editor' ).getEditedPostAttribute( 'meta' )._anwp_be_priority || 10;
	} );

	const { editPost } = useDispatch( 'core/editor' );

	return (
		<>
			<TextControl
				label="Action Hook"
				value={ hookValue }
				onChange={ ( value ) => editPost( { meta: { _anwp_be_hook: value } } ) }
				help="Enter the WordPress action hook name (e.g., wp_footer, wp_head)"
			/>
			<TextControl
				label="Priority"
				type="number"
				value={ priorityValue }
				onChange={ ( value ) => editPost( { meta: { _anwp_be_priority: parseInt( value ) || 10 } } ) }
				help="Lower numbers run earlier (default: 10)"
			/>
		</>
	);
};

registerPlugin(
	'anwp-be-plugin',
	{
		render: () => {
			return (
				<PluginDocumentSettingPanel
					title="AnWP Blocks Everywhere"
					icon="menu"
				>
					<PanelBody>
						<TopMenuItemSelector/>
					</PanelBody>
				</PluginDocumentSettingPanel>
			);
		}
	} );
