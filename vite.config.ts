import { createAppConfig } from '@nextcloud/vite-config'
import { join, resolve } from 'path'

export default createAppConfig(
	{
		main: resolve(join('src', 'main.ts')),
	},
	{
		createEmptyCSSEntryPoints: false,
		extractLicenseInformation: true,
		thirdPartyLicense: false,
		inlineCSS: true,
		assetFileNames: (assetInfo: any) => {
			const names = assetInfo.names || (assetInfo.name ? [assetInfo.name] : [])
			if (names.some((n: string) => n.endsWith('.css'))) {
				return 'css/nopstation_analytics-main.css'
			}
			return undefined
		},
		config: {
			build: {
				cssCodeSplit: false,
			},
		},
	},
)
