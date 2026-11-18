/**
 * Formats a decimal amount in the es-DO locale with the account's currency (spec 001, R8;
 * D-084, D-093). The amount stays a string all the way into Intl, never a JavaScript number
 * (D-078). The cast only satisfies the `${number}` type of format(); nothing is converted.
 * signDisplay 'negative' keeps a -0.00 left by a subtraction or rounding from showing as "-RD$0.00".
 */
export function formatMoney(amount: string, currency: string): string {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency,
        currencyDisplay: 'symbol',
        signDisplay: 'negative',
    }).format(amount as Intl.StringNumericLiteral);
}
