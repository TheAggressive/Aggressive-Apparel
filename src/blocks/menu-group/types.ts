export interface MenuGroupAttributes {
  title?: string;
  layout: 'vertical' | 'horizontal' | 'grid';
  columns?: number;
  gap?: string;
  showTitle: boolean;
}
