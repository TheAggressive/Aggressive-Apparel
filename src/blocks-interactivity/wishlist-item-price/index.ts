import metadata from './block.json';
import blockIcon from './icon';
import Edit from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

registerThemeBlock(metadata, {
  icon: blockIcon,
  edit: Edit,
});
