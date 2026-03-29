export interface RouteNode {
    id: string;
    label: string;
    node_type: 'label' | 'choice' | 'hub';
    is_ending: boolean;
    is_start: boolean;
    has_menu_choice: boolean;
    file_path: string | null;
    line_number: number;
    outgoing_count: number;
    word_count: number;
    choices: MenuChoice[];
    variable_changes: VariableChange[];
    parent_label?: string;
    condition?: string | null;
    menu_prompt?: string | null;
}

export interface MenuChoice {
    text: string | null;
    target_label: string | null;
    condition: string | null;
}

export interface VariableChange {
    variable: string;
    operation: string;
    value: string | null;
    context: string | null;
}

export interface RouteEdge {
    id: string;
    source: string;
    target: string;
    edge_type: string;
    choice_text?: string | null;
    condition?: string | null;
    file_path?: string | null;
    line_number?: number;
}

export interface RouteVariable {
    name: string;
    default_value: string | null;
    type: string;
    change_count: number;
}

export interface SimplifiedNode {
    id: string;
    label: string;
    type: 'branch' | 'chain';
    is_start: boolean;
    is_ending: boolean;
    word_count: number;
    chain_labels?: string[];
    first_label?: string;
    last_label?: string;
}

export interface SimplifiedEdge {
    id: string;
    source: string;
    target: string;
    edge_type: string;
}

export interface RouteGraphData {
    nodes: RouteNode[];
    edges: RouteEdge[];
    variables: RouteVariable[];
    endings: string[];
    total_nodes: number;
    total_edges: number;
    simplified: {
        nodes: SimplifiedNode[];
        edges: SimplifiedEdge[];
        chain_count: number;
    };
    has_graph_data: boolean;
}

export interface RouteMapPageProps {
    game: {
        id: number;
        name: string;
        slug: string;
        thumbnail_url: string | null;
    };
    currentVersion: {
        id: number;
        version: string;
    };
    gameVersions: Array<{
        id: number;
        version: string;
    }>;
    routeGraph: RouteGraphData;
    availableLanguages: string[];
    currentLanguage: string | null;
    metaTags: {
        title: string;
        description: string;
    };
}
