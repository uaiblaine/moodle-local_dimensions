export const getStrings = (requests: {key: string, component: string}[]): Promise<string[]> =>
    Promise.resolve(requests.map((r) => `${r.component}:${r.key}`));
