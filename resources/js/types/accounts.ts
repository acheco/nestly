export type AccountType =
    | 'checking'
    | 'savings'
    | 'cash'
    | 'credit'
    | 'investment';
export type AccountVisibility = 'team' | 'private';
export type Account = {
    id: string;
    name: string;
    type: AccountType;
    visibility: AccountVisibility;
    archived: boolean;
    owner: { id: string; name: string } | null;
    initialBalance: string | null;
    currentBalance: string | null;
    can: {
        update: boolean;
        archive: boolean;
        delete: boolean;
    };
};
