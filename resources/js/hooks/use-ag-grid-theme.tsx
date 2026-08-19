import type { Theme } from 'ag-grid-community';
import { iconSetAlpine, themeQuartz } from 'ag-grid-community';
import { useMemo } from 'react';
import { useAppearance } from './use-appearance';

export const useAgGridTheme = (): Theme => {
    const { appearance } = useAppearance();

    return useMemo(() => {
        const isDark = appearance === 'dark';

        return themeQuartz.withPart(iconSetAlpine).withParams({
            accentColor: isDark ? '#68FF8E' : '#16A34A',
            backgroundColor: isDark ? '#21222C' : '#FFFFFF',
            borderColor: isDark ? '#429356' : '#86EFAC',
            browserColorScheme: isDark ? 'dark' : 'light',
            cellHorizontalPaddingScale: 0.8,
            cellTextColor: isDark ? '#50F178' : '#166534',
            columnBorder: true,
            fontFamily: {
                googleFont: "Roboto",
            },
            foregroundColor: isDark ? '#68FF8E' : '#15803D',
            headerBackgroundColor: isDark ? '#21222C' : '#F0FDF4',
            headerFontWeight: 800,
            headerHeight: 25,
            headerTextColor: isDark ? '#68FF8E' : '#166534',
            headerVerticalPaddingScale: 1.5,
            oddRowBackgroundColor: isDark ? '#21222C' : '#F0FDF4',
            rangeSelectionBackgroundColor: isDark
                ? '#FFFF0020'
                : '#22C55E20',
            rangeSelectionBorderColor: isDark
                ? 'yellow'
                : '#16A34A',
            rangeSelectionBorderStyle: 'dashed',
            rowBorder: true,
            rowHeight: 21,
            rowVerticalPaddingScale: 1.5,
            sidePanelBorder: true,
            spacing: 4,
        });
    }, [appearance]);
};
