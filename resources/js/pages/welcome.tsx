import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Calculator, History, Package, ShieldCheck } from 'lucide-react';

const FEATURES = [
    {
        icon: Calculator,
        title: 'Цена за секунди',
        description: 'Въведете материали, труд и желан марж — крайната цена се пресмята в реално време.',
    },
    {
        icon: Package,
        title: 'Каталог с материали',
        description: 'Запазете често използваните артикули с мерна единица и стандартна цена.',
    },
    {
        icon: History,
        title: 'Непроменяема история',
        description: 'Всяка записана калкулация пази точните стойности от момента на изчислението.',
    },
    {
        icon: ShieldCheck,
        title: 'Коректни данъци',
        description: 'Корпоративен данък и данък дивидент са фиксирани в кода и не се променят от интерфейса.',
    },
];

export default function Welcome() {
    const { auth, app } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Decorron — бързо ценообразуване за декорация на събития" />

            <div className="bg-background text-foreground flex min-h-svh flex-col">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-6 py-5">
                    <div className="flex items-center gap-2">
                        <div className="bg-primary text-primary-foreground flex size-9 items-center justify-center rounded-md">
                            <AppLogoIcon className="size-5" />
                        </div>
                        <span className="text-lg font-semibold">Decorron</span>
                    </div>

                    <nav className="flex items-center gap-2">
                        {auth?.user ? (
                            <Button asChild size="sm">
                                <Link href={route('dashboard')}>Към таблото</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild size="sm" variant="ghost">
                                    <Link href={route('login')}>Вход</Link>
                                </Button>
                                {app?.registration_enabled && (
                                    <Button asChild size="sm">
                                        <Link href={route('register')}>Регистрация</Link>
                                    </Button>
                                )}
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto w-full max-w-5xl flex-1 px-6 py-10">
                    <h1 className="max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">
                        Бързо ценообразуване за балонени арки, фотозони и украса за събития
                    </h1>
                    <p className="text-muted-foreground mt-4 max-w-2xl text-base">
                        Decorron пресмята крайната цена за клиента от материалите, труда и желания чист марж — включително корпоративния данък и
                        данъка върху дивидента. Направено е за работа от телефон, докато говорите с клиента.
                    </p>

                    <div className="mt-8">
                        <Button asChild size="lg">
                            <Link href={auth?.user ? route('calculations.create') : route('login')}>
                                {auth?.user ? 'Нова калкулация' : 'Вход в системата'}
                            </Link>
                        </Button>
                    </div>

                    <ul className="mt-12 grid gap-4 sm:grid-cols-2">
                        {FEATURES.map((feature) => (
                            <li key={feature.title} className="rounded-xl border p-5">
                                <feature.icon className="text-primary size-5" aria-hidden="true" />
                                <h2 className="mt-3 font-medium">{feature.title}</h2>
                                <p className="text-muted-foreground mt-1 text-sm">{feature.description}</p>
                            </li>
                        ))}
                    </ul>
                </main>

                <footer className="text-muted-foreground mx-auto w-full max-w-5xl px-6 py-8 text-xs">
                    Приложението използва опростен модел за ценообразуване и не замества индивидуална счетоводна консултация.
                </footer>
            </div>
        </>
    );
}
