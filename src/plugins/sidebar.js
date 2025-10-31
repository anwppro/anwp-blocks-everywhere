import {registerPlugin} from '@wordpress/plugins';
import {PluginDocumentSettingPanel} from '@wordpress/edit-post';
import {PanelBody, TextControl} from '@wordpress/components';
import {useSelect, useDispatch} from '@wordpress/data';

const TopMenuItemSelector = () => {

	const hookValue = useSelect( ( select ) => {
		return select( 'core/editor' ).getEditedPostAttribute( 'meta' )._anwp_be_hook;
	} );

	const { editPost } = useDispatch( 'core/editor' );

	return (
		<TextControl
			label="Action Hook"
			value={ hookValue }
			onChange={ ( value ) => editPost( { meta: { _anwp_megamenu_id: value } } ) }
		/>
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
