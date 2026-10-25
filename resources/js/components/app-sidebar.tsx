import { NavUser } from '@/components/nav-user';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { isActiveSection, isActiveUrl, readyItems, readySections, sectionUrl, type NavSection } from '@/lib/navigation';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const page = usePage();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup className="px-2 py-0">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                tooltip="Нова калкулация"
                                className="bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground active:bg-primary active:text-primary-foreground"
                            >
                                <Link href="/calculations/create">
                                    <Plus />
                                    <span>Нова калкулация</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>

                <SidebarGroup className="px-2 py-0">
                    <SidebarMenu>
                        {readySections().map((section) => (
                            <SectionMenuItem key={section.key} section={section} currentUrl={page.url} />
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

function SectionMenuItem({ section, currentUrl }: { section: NavSection; currentUrl: string }) {
    const items = readyItems(section);
    const sectionActive = isActiveSection(section, currentUrl);
    const [open, setOpen] = useState(sectionActive);

    // Активната секция се разгъва автоматично при навигация.
    useEffect(() => {
        if (sectionActive) {
            setOpen(true);
        }
    }, [sectionActive]);

    if (items.length === 0) {
        return (
            <SidebarMenuItem>
                <SidebarMenuButton asChild isActive={sectionActive} tooltip={section.title}>
                    <Link href={sectionUrl(section)} prefetch>
                        <section.icon />
                        <span>{section.title}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        );
    }

    return (
        <Collapsible open={open} onOpenChange={setOpen} className="group/collapsible">
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton isActive={sectionActive} tooltip={section.title}>
                        <section.icon />
                        <span>{section.title}</span>
                        <ChevronRight
                            className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                            aria-hidden="true"
                        />
                    </SidebarMenuButton>
                </CollapsibleTrigger>

                <CollapsibleContent>
                    <SidebarMenuSub>
                        {items.map((item) => (
                            <SidebarMenuSubItem key={item.url}>
                                <SidebarMenuSubButton asChild isActive={isActiveUrl(item.url, currentUrl)}>
                                    <Link href={item.url} prefetch aria-current={isActiveUrl(item.url, currentUrl) ? 'page' : undefined}>
                                        <item.icon />
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}
