/*
 * Résolution des icônes de nodes : le catalogue backend expose le nom
 * lucide (métadonnée `icon`), le front le mappe sur le composant.
 * Aucune id de type de node n'apparaît ici — seul le nom d'icône
 * générique est résolu, avec un repli muet pour tout nom inconnu
 * (le catalogue peut évoluer côté backend sans retouche front).
 */
import type { Component } from 'vue';
import {
    ArrowRightToLine,
    Bot,
    Braces,
    CalendarClock,
    FileText,
    Filter,
    GitBranch,
    Globe,
    Mail,
    MessageSquare,
    Play,
    Shuffle,
    Sparkles,
    Timer,
    Webhook,
    type LucideProps,
} from '@lucide/vue';

const nodeIcons: Record<string, Component<LucideProps>> = {
    webhook: Webhook,
    'calendar-clock': CalendarClock,
    play: Play,
    braces: Braces,
    shuffle: Shuffle,
    globe: Globe,
    'arrow-right-to-line': ArrowRightToLine,
    'git-branch': GitBranch,
    filter: Filter,
    bot: Bot,
    sparkles: Sparkles,
    'file-text': FileText,
    mail: Mail,
    'message-square': MessageSquare,
    timer: Timer,
};

const fallbackIcon = Braces;

/** Composant icône lucide pour un nom d'icône du catalogue. */
export function nodeIcon(
    name: string | null | undefined,
): Component<LucideProps> {
    return (
        (name !== null && name !== undefined && nodeIcons[name]) || fallbackIcon
    );
}
