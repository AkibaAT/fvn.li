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
    // Fields added during client-side processing
    choice_text?: string;
    translations?: Record<string, string>;
    menu_prompt_translations?: Record<string, string> | null;
    var_summary?: string | null;
    chain_labels?: string[];
}

export interface MenuChoice {
    text: string | null;
    target_label: string | null;
    condition: string | null;
    translations?: Record<string, string>;
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

export interface RouteGraphData {
    nodes: RouteNode[];
    edges: RouteEdge[];
    variables: RouteVariable[];
    endings: string[];
    total_nodes: number;
    total_edges: number;
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

export interface DisplayNode {
    id: string;
    type: 'choice' | 'hub' | 'label' | undefined;
    data: RouteNode & {
        label?: string;
        choice_text?: string;
        translations?: Record<string, string>;
        var_summary?: string | null;
        menu_prompt_translations?: Record<string, string> | null;
        chain_labels?: string[];
        [key: string]: unknown;
    };
    position: { x: number; y: number };
    style?: string;
    class?: string;
}

export interface DisplayEdge {
    id: string;
    source: string;
    target: string;
    type: string;
    animated?: boolean;
    label?: string;
    data: RouteEdge;
    style?: string;
}

export interface NavigationStep {
    step: number;
    nodeId: string;
    edgeType: string;
    isChoice: boolean;
    choiceText: string | null;
    condition: string | null;
    targetIsEnding: boolean;
}
