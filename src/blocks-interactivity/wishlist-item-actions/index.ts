import metadata from './block.json';
import blockIcon from './icon';
import Edit, { type Attrs } from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

registerThemeBlock<Attrs>(metadata, {
  icon: blockIcon,
  edit: Edit,
});
