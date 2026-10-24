/**
 * Разрешаване на Inertia страници от приложението и от модулите.
 *
 * Имената на страниците имат два вида:
 *   "welcome"                     → resources/js/pages/welcome.tsx
 *   "pricing::calculations/create" → modules/Pricing/Resources/js/pages/calculations/create.tsx
 *
 * Така всеки модул носи собствения си frontend и добавянето на нов модул
 * (CRM, Склад, График) не изисква промени в този файл.
 */

const appPages = import.meta.glob('../pages/**/*.tsx');
const modulePages = import.meta.glob('../../../modules/*/Resources/js/pages/**/*.tsx');

type PageModule = { default: unknown };

function normalise(path: string): string {
    return path.toLowerCase();
}

export async function resolvePage(name: string): Promise<PageModule> {
    if (name.includes('::')) {
        const [moduleName, pagePath] = name.split('::');
        const suffix = normalise(`/modules/${moduleName}/resources/js/pages/${pagePath}.tsx`);

        const match = Object.keys(modulePages).find((key) => normalise(key).endsWith(suffix));

        if (!match) {
            throw new Error(`Страницата "${name}" не беше намерена в модул "${moduleName}".`);
        }

        return modulePages[match]() as Promise<PageModule>;
    }

    const key = `../pages/${name}.tsx`;

    if (!appPages[key]) {
        throw new Error(`Страницата "${name}" не беше намерена.`);
    }

    return appPages[key]() as Promise<PageModule>;
}
