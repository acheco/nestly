import { parse } from 'vue/compiler-sfc';

export type UntranslatedText = {
    line: number;
    text: string;
};

// Values of Vue's NodeTypes enum (@vue/compiler-core), which vue/compiler-sfc
// does not re-export and is not a direct dependency.
const TEXT = 2;
const ATTRIBUTE = 6;

/**
 * Static attributes whose value is shown or read out to the user. Bound
 * attributes (`:placeholder="t('…')"`) are directives and are not checked.
 */
const TEXT_ATTRIBUTES = new Set([
    'placeholder',
    'aria-label',
    'title',
    'alt',
    'label',
    'description',
]);

const HAS_LETTERS = /\p{L}/u;

type TemplateNode = {
    type: number;
    name?: string;
    content?: string;
    value?: { content: string };
    loc: { start: { line: number } };
    props?: TemplateNode[];
    children?: TemplateNode[];
};

function collect(node: TemplateNode, found: UntranslatedText[]): void {
    if (
        node.type === TEXT &&
        node.content !== undefined &&
        HAS_LETTERS.test(node.content)
    ) {
        found.push({ line: node.loc.start.line, text: node.content.trim() });
    }

    for (const prop of node.props ?? []) {
        if (
            prop.type === ATTRIBUTE &&
            TEXT_ATTRIBUTES.has(prop.name ?? '') &&
            prop.value !== undefined &&
            HAS_LETTERS.test(prop.value.content)
        ) {
            found.push({
                line: prop.loc.start.line,
                text: `${prop.name}="${prop.value.content}"`,
            });
        }
    }

    for (const child of node.children ?? []) {
        collect(child, found);
    }
}

/**
 * Text a .vue template shows without going through t(): text nodes and the
 * static text attributes above that contain letters (spec 001, R1; D-091).
 * Literals inside <script> or template expressions are out of its reach.
 */
export function findUntranslatedText(
    source: string,
    fileName: string,
): UntranslatedText[] {
    const { descriptor } = parse(source, { filename: fileName });
    const found: UntranslatedText[] = [];

    if (descriptor.template?.ast) {
        collect(descriptor.template.ast as unknown as TemplateNode, found);
    }

    return found;
}
