import ts from 'typescript';
import { parse } from 'vue/compiler-sfc';

export type TranslationCall = {
    line: number;
    key: string | null;
    code: string;
};

/**
 * `import`: an import of lib/i18n that renames t or imports it as a
 * namespace. `reserved`: any other use or declaration of the identifier t,
 * which is reserved for the helper (D-091).
 */
export type TranslationEvasion = {
    line: number;
    code: string;
    reason: 'import' | 'reserved';
};

export type TranslationScan = {
    calls: TranslationCall[];
    evasions: TranslationEvasion[];
};

// Values of Vue's NodeTypes enum (@vue/compiler-core), which vue/compiler-sfc
// does not re-export and is not a direct dependency.
const ELEMENT = 1;
const INTERPOLATION = 5;
const DIRECTIVE = 7;

type Expression = { content: string; loc: { start: { line: number } } };

type TemplateNode = {
    type: number;
    name?: string;
    exp?: Expression;
    content?: Expression;
    props?: TemplateNode[];
    children?: TemplateNode[];
};

function isI18nModule(specifier: string): boolean {
    return (
        specifier === '@/lib/i18n' ||
        (specifier.startsWith('.') && /(^|\/)lib\/i18n(\.ts)?$/.test(specifier))
    );
}

/**
 * Whether an identifier named `t` breaks the reservation of t for the
 * helper (D-091): a value use (`items.map(t)`, `t.name`, `{ t }`) or a
 * declaration (variable, parameter, destructuring, function, class). A local
 * t would shadow the helper, and passing the helper around hides its calls.
 * Callees and property names (`obj.t`, `{ t: 1 }`, methods,
 * `const { t: x } = obj`) are allowed.
 */
function isReservedUse(identifier: ts.Identifier): boolean {
    const parent = identifier.parent;

    if (ts.isCallExpression(parent) && parent.expression === identifier) {
        return false;
    }

    const isPropertyName =
        (ts.isPropertyAccessExpression(parent) ||
            ts.isPropertyAssignment(parent) ||
            ts.isPropertyDeclaration(parent) ||
            ts.isPropertySignature(parent) ||
            ts.isMethodDeclaration(parent) ||
            ts.isMethodSignature(parent) ||
            ts.isGetAccessorDeclaration(parent) ||
            ts.isSetAccessorDeclaration(parent) ||
            ts.isEnumMember(parent)) &&
        parent.name === identifier;

    return (
        !isPropertyName &&
        !(ts.isBindingElement(parent) && parent.propertyName === identifier)
    );
}

/**
 * The first line of the statement that contains `node`, to show in errors.
 */
function statementText(node: ts.Node, file: ts.SourceFile): string {
    let statement = node;

    while (
        statement.parent &&
        !ts.isSourceFile(statement.parent) &&
        !ts.isBlock(statement.parent)
    ) {
        statement = statement.parent;
    }

    return statement.getText(file).split('\n')[0].trim();
}

/**
 * Parses a piece of TypeScript and records every call to the bare identifier
 * `t`, plus what would let calls escape this scan: imports of lib/i18n that
 * rename `t` or import it as a namespace, and any other use or declaration
 * of t. `firstLine` is the file line on which `code` starts; `display`, when
 * given, replaces the parsed code in error messages.
 */
function scanCode(
    code: string,
    firstLine: number,
    scan: TranslationScan,
    display?: string,
): void {
    const file = ts.createSourceFile(
        'fragment.ts',
        code,
        ts.ScriptTarget.Latest,
        true,
        ts.ScriptKind.TS,
    );
    const lineOf = (node: ts.Node): number =>
        firstLine +
        file.getLineAndCharacterOfPosition(node.getStart(file)).line;

    const visit = (node: ts.Node): void => {
        if (
            ts.isCallExpression(node) &&
            ts.isIdentifier(node.expression) &&
            node.expression.text === 't'
        ) {
            const [first] = node.arguments;

            scan.calls.push({
                line: lineOf(node),
                key: first && ts.isStringLiteral(first) ? first.text : null,
                code: node.getText(file),
            });
        }

        if (ts.isImportDeclaration(node)) {
            const bindings = node.importClause?.namedBindings;
            const hidesT =
                ts.isStringLiteral(node.moduleSpecifier) &&
                isI18nModule(node.moduleSpecifier.text) &&
                bindings !== undefined &&
                (ts.isNamespaceImport(bindings) ||
                    bindings.elements.some(
                        (element) =>
                            element.propertyName?.text === 't' &&
                            element.name.text !== 't',
                    ));

            if (hidesT) {
                scan.evasions.push({
                    line: lineOf(node),
                    code: node.getText(file),
                    reason: 'import',
                });
            }

            // A `t` inside any import is allowed: `import { t }` is how the
            // helper arrives.
            return;
        }

        if (ts.isIdentifier(node) && node.text === 't' && isReservedUse(node)) {
            scan.evasions.push({
                line: lineOf(node),
                code: display ?? statementText(node, file),
                reason: 'reserved',
            });
        }

        ts.forEachChild(node, visit);
    };

    visit(file);
}

/**
 * Scans parameter lists (a v-for alias, v-slot props) as the parameters of
 * an arrow function, so a parameter named t is caught like in a script.
 */
function scanParameters(
    parameters: string,
    exp: Expression,
    scan: TranslationScan,
): void {
    const list = parameters.trim().replace(/^\(([\s\S]*)\)$/, '$1');

    scanCode(`(${list}) => 0`, exp.loc.start.line, scan, exp.content);
}

/**
 * Scans the expressions of a template AST: interpolations and directive
 * values. Static attributes are text, not code. Expressions are wrapped in
 * parentheses so `{ a: b }` parses as an object, not a block; v-on handlers
 * are statements and stay as they are. v-for has an alias and a list, and
 * v-slot holds parameters.
 */
function scanTemplate(node: TemplateNode, scan: TranslationScan): void {
    if (node.type === INTERPOLATION && node.content) {
        const { content, loc } = node.content;

        scanCode(`(${content})`, loc.start.line, scan, content);
    }

    if (node.type === ELEMENT) {
        for (const prop of node.props ?? []) {
            if (prop.type !== DIRECTIVE || !prop.exp) {
                continue;
            }

            const { content, loc } = prop.exp;

            if (prop.name === 'slot') {
                scanParameters(content, prop.exp, scan);

                continue;
            }

            if (prop.name === 'for') {
                const separator = /\s(?:in|of)\s/.exec(content);

                if (separator) {
                    scanParameters(
                        content.slice(0, separator.index),
                        prop.exp,
                        scan,
                    );

                    // Blank the alias so the list keeps its line numbers.
                    const list =
                        content
                            .slice(0, separator.index + separator[0].length)
                            .replace(/[^\n]/g, ' ') +
                        content.slice(separator.index + separator[0].length);

                    scanCode(`(${list})`, loc.start.line, scan, content);
                }

                continue;
            }

            scanCode(
                prop.name === 'on' ? content : `(${content})`,
                loc.start.line,
                scan,
                content,
            );
        }
    }

    for (const child of node.children ?? []) {
        scanTemplate(child, scan);
    }
}

/**
 * Finds every t() call, every import that hides t() and every other use or
 * declaration of the reserved identifier t in a .ts or .vue source
 * (spec 001, R2; D-091).
 */
export function findTranslationCalls(
    source: string,
    fileName: string,
): TranslationScan {
    const scan: TranslationScan = { calls: [], evasions: [] };

    if (!fileName.endsWith('.vue')) {
        scanCode(source, 1, scan);

        return scan;
    }

    const { descriptor } = parse(source, { filename: fileName });

    for (const block of [descriptor.script, descriptor.scriptSetup]) {
        if (block) {
            scanCode(block.content, block.loc.start.line, scan);
        }
    }

    if (descriptor.template?.ast) {
        scanTemplate(descriptor.template.ast as unknown as TemplateNode, scan);
    }

    scan.calls.sort((a, b) => a.line - b.line);
    scan.evasions.sort((a, b) => a.line - b.line);

    return scan;
}
