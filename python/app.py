from dash import Dash, html, dcc, Input, Output, State, dash_table
import urllib.parse
import pandas as pd
import plotly.express as px
from sqlalchemy import create_engine, text
import hmac
import hashlib
import time
import os

app = Dash(__name__, suppress_callback_exceptions=True)
server = app.server  # Required for Gunicorn

# Establish Database Connection Pool via SQLAlchemy
DB_URI = "postgresql+psycopg2://postgres:password123@127.0.0.1:5432/my_dash_db"
engine = create_engine(DB_URI)

app.layout = html.Div(style={'fontFamily': 'ui-sans-serif, system-ui, sans-serif', 'padding': '5px'}, children=[
    dcc.Location(id='url-tracker', refresh=False),
    
    # Internal Browser memory register cache storage to drop database requirements down to 1 call
    dcc.Store(id='postgres-data-cache'),
    
    html.H2("Embedded Analytics Engine", style={'color': '#0F172A', 'fontSize': '1.25rem', 'fontWeight': '700', 'margin': '0 0 4px 0'}),
    html.Div(id='status-banner', style={'fontSize': '0.875rem', 'color': '#64748B', 'marginBottom': '20px'}),
    
    # Grid Flex-columns layout wrapper mapping to device windows
    html.Div(
        id='dash-root-container',
        style={'display': 'flex', 'flexWrap': 'wrap', 'gap': '24px', 'width': '100%'},
        children=[
            # Column 1: Donut Graphic Mount Target
            html.Div(id='chart-output-mount', style={'flex': '1 1 400px', 'minWidth': '320px'}),
            
            # Column 2: Structural Data Grid Mount Target
            html.Div(id='table-output-mount', style={'flex': '1 2 550px', 'minWidth': '320px'})
        ]
    )
])

SECRET_KEY = os.getenv("APP_SHARED_SECRET", "").encode('utf-8')

def verify_signature(user_id, date_range, expires, signature):
    if not all([user_id, date_range, expires, signature]):
        return False
    # 1. Check if the link has expired
    if int(time.time()) > int(expires):
        return False
    # 2. Recompute expected HMAC signature
    payload = f"{user_id}|{date_range}|{expires}".encode('utf-8')
    expected_sig = hmac.new(SECRET_KEY, payload, hashlib.sha256).hexdigest()
    # 3. Use constant-time comparison to prevent timing attacks
    return hmac.compare_digest(expected_sig, signature)

@server.after_request
def add_security_headers(response):
    # Replace with your actual PHP frontend domain in production
    response.headers['Content-Security-Policy'] = "frame-ancestors 'self' http://localhost:* http://127.0.0.1:* https://yourdomain.com;"
    response.headers['X-Content-Type-Options'] = 'nosniff'
    return response

# ----------------------------------------------------------------------------------
# PIPELINE STEP A: Database Fetching & Caching Sequence
# ----------------------------------------------------------------------------------
@app.callback(
    [Output('status-banner', 'children'),
     Output('chart-output-mount', 'children'),
     Output('postgres-data-cache', 'data')],
    [Input('url-tracker', 'search')]
)
def initial_data_load(search_string):
    if not search_string:
        return "System Notification: Awaiting authentication parameters.", "", None
    
    parsed_queries = urllib.parse.parse_qs(search_string.lstrip('?'))
    user_id_raw = parsed_queries.get('user_id', [None])[0]
    date_range_raw = parsed_queries.get('date_range', [None])[0]
    
    if not user_id_raw:
        return "System Exception: Missing targeting keys.", "", None

    # Deconstruct unified tracking date-range arguments
    start_bound, end_bound = None, None
    if date_range_raw and "_to_" in date_range_raw:
        try:
            date_splits = date_range_raw.split("_to_")
            start_bound = f"{date_splits[0]} 00:00:00"
            end_bound = f"{date_splits[1]} 23:59:59"
        except Exception:
            pass

    # Parameterized SQL statements avoiding injection risks
    if start_bound and end_bound:
        sql_query = text("""
            SELECT transaction_id, item_name, amount, purchase_date 
            FROM user_transactions 
            WHERE user_id = :uid 
              AND purchase_date BETWEEN :start_d AND :end_d
            ORDER BY purchase_date DESC
        """)
        execution_parameters = {"uid": int(user_id_raw), "start_d": start_bound, "end_d": end_bound}
    else:
        sql_query = text("""
            SELECT transaction_id, item_name, amount, purchase_date 
            FROM user_transactions 
            WHERE user_id = :uid 
            ORDER BY purchase_date DESC
        """)
        execution_parameters = {"uid": int(user_id_raw)}

    try:
        with engine.connect() as database_connection:
            dataframe = pd.read_sql_query(sql_query, database_connection, params=execution_parameters)
            
        if dataframe.empty:
            return f"Session Profile ID: {user_id_raw} connected.", html.Div("Zero ledger history records detected.", style={'color': '#94A3B8'}), None

        # Standardize timing matrix representation strings for structural grids
        dataframe['purchase_date'] = dataframe['purchase_date'].dt.strftime('%Y-%m-%d %H:%M')

        # Aggregate total category metrics to feed the Donut visual components
        aggregated_df = dataframe.groupby('item_name', as_index=False)['amount'].sum()
        
        # Microtheme adjustments mirroring Tailwind palette configurations
        tailwind_palette = ['#4F46E5', '#8B5CF6', '#A855F7', '#D946EF', '#EC4899']
        
        fig = px.pie(
            aggregated_df, 
            names='item_name', 
            values='amount',
            hole=0.4,
            title="Spending Summary Allocation",
            color_discrete_sequence=tailwind_palette
        )
        fig.update_layout(
            margin=dict(l=10, r=10, t=40, b=10),
            paper_bgcolor='rgba(0,0,0,0)',
            title_font=dict(size=14, color='#334155', family="ui-sans-serif, system-ui, sans-serif", weight="bold"),
            clickmode='event+select'
        )
        fig.update_traces(textinfo='percent+label', textfont_size=11, marker=dict(line=dict(color='#FFFFFF', width=2)))
        
        chart_component = dcc.Graph(id='donut-chart', figure=fig, config={'displayModeBar': False})
        status_msg = f"Successfully generated analytics matrix matching query properties from primary schema database engine."
        
        return status_msg, chart_component, dataframe.to_dict('records')

    except Exception as query_fault_exception:
        return "Critical Database Connection Error.", html.Pre(str(query_fault_exception), style={'color': '#EF4444'}), None


# ----------------------------------------------------------------------------------
# PIPELINE STEP B: Dynamic Client-Side In-Memory Filtering Loop
# ----------------------------------------------------------------------------------
@app.callback(
    Output('donut-chart', 'clickData'),
    Input('reset-chart-filter-btn', 'n_clicks'),
    prevent_initial_call=True
)
def reset_chart_selection(n_clicks):
    return None

@app.callback(
    Output('table-output-mount', 'children'),
    [Input('donut-chart', 'clickData'),
     Input('postgres-data-cache', 'data')]
)
def filter_table_by_chart_click(click_data, cached_records):
    if not cached_records:
        return html.P("No records found in storage context pool.")
        
    df = pd.DataFrame(cached_records)
    selected_item = None

    # Listen to click interaction event hooks map metrics arrays
    if click_data and 'points' in click_data:
        try:
            selected_item = click_data['points'][0]['label']
            df = df[df['item_name'] == selected_item]
        except Exception:
            selected_item = None 

    # Header label & Reset button (visible only when filtered, or always rendered for callback binding)
    filter_badge = (
        f"Transaction Ledger — Filtered: {selected_item}"
        if selected_item
        else f"Transaction Ledger (All {len(df)} records)"
    )

    reset_btn_style = {
        'display': 'inline-block' if selected_item else 'none',
        'backgroundColor': '#F1F5F9',
        'color': '#334155',
        'border': '1px solid #CBD5E1',
        'borderRadius': '8px',
        'padding': '6px 12px',
        'fontSize': '0.8rem',
        'fontWeight': '600',
        'cursor': 'pointer'
    }

    # # Add an 'actions' column with Markdown links for Edit and Delete
    # df_display = df.copy()
    # df_display['actions'] = "✏️ Edit  |  🗑️ Delete"

    # columns_config = [
    #     {"name": column.replace('_', ' ').title(), "id": column}
    #     for column in df.columns
    # ]
    # # Append the interactive Actions column
    # columns_config.append({"name": "Actions", "id": "actions"})

    # Construct the Tailwind CSS Styled Slate DataTable
    ui_data_grid = dash_table.DataTable(
        id='transactions-datatable',
        data=df.to_dict('records'),
        columns=[{"name": column.replace('_', ' ').title(), "id": column} for column in df.columns],
        # data=df_display.to_dict('records'),
        # columns=columns_config,
        # ---AUTO PAGINATION CONFIGURATION ---
        page_action='native',   # Enables Dash's built-in automatic pagination
        page_current=0,         # Starting from the first page (index 0)
        page_size=5,            # Displays 5 rows per page (change as needed)
        
        # Optional: Enable the automatic column sorting feature
        sort_action='native',
        style_table={'border': '1px solid #E2E8F0', 'borderRadius': '12px', 'overflow': 'hidden'},
        style_header={
            'backgroundColor': '#F8FAFC',
            'fontWeight': '600',
            'color': '#334155',
            'padding': '12px 16px',
            'borderBottom': '2px solid #E2E8F0',
            'fontSize': '0.875rem'
        },
        style_cell={
            'padding': '12px 16px',
            'fontSize': '0.875rem',
            'color': '#475569',
            'borderBottom': '1px solid #F1F5F9',
            'fontFamily': 'ui-sans-serif, system-ui, sans-serif'
        },
        style_data_conditional=[
            {'if': {'row_index': 'odd'}, 'backgroundColor': '#FFFFFF'},
            {'if': {'row_index': 'even'}, 'backgroundColor': '#FAFAFA'},
            # # Style the Actions column to look like interactive buttons
            # {
            #     'if': {'column_id': 'actions'},
            #     'color': '#4F46E5',
            #     'fontWeight': '600',
            #     'cursor': 'pointer',
            #     'textAlign': 'right'
            # }
        ]
    )

    # Wrap the table with a top bar containing the "+ Insert Transaction" button
    return html.Div([
        html.Div(
            style={'display': 'flex', 'justifyContent': 'space-between', 'alignItems': 'center', 'marginBottom': '12px', 'gap': '8px'},
            children=[
                html.Span(filter_badge, style={'fontWeight': '600', 'color': '#1E293B', 'fontSize': '0.9rem'}),
                html.Div(
                    style={'display': 'flex', 'gap': '8px'},
                    children=[
                        html.Button(
                            "↺ Show All",
                            id="reset-chart-filter-btn",
                            n_clicks=0,
                            style=reset_btn_style
                        ),
                        html.Button(
                            "+ Insert / Manage Data",
                            id="dash-open-crud-btn",
                            n_clicks=0,
                            style={
                                'backgroundColor': '#4F46E5',
                                'color': '#FFFFFF',
                                'border': 'none',
                                'borderRadius': '8px',
                                'padding': '6px 14px',
                                'fontSize': '0.8rem',
                                'fontWeight': '600',
                                'cursor': 'pointer'
                            }
                        )
                    ]
                )
            ]
        ),
        ui_data_grid,
        dcc.Store(id='parent-modal-trigger')
    ])

# ----------------------------------------------------------------------------------
# PIPELINE STEP C: Bridge Dash CRUD Button & Table Row Clicks to Parent PHP Modal
# ----------------------------------------------------------------------------------
app.clientside_callback(
    """
    function(n_clicks, active_cell, table_data) {
        const ctx = dash_clientside.callback_context;
        if (!ctx.triggered.length) {
            return window.dash_clientside.no_update;
        }
        
        const triggerId = ctx.triggered[0].prop_id;
        
        // 1. User clicked "+ Insert / Manage Data" button above the table
        if (triggerId === 'dash-open-crud-btn.n_clicks' && n_clicks > 0) {
            window.parent.postMessage({ type: 'OPEN_CRUD_MODAL', mode: 'create' }, '*');
        }
        
        // 2. User clicked the "Actions" column on a specific table row
        if (triggerId === 'transactions-datatable.active_cell' && active_cell && active_cell.column_id === 'actions') {
            const row = table_data[active_cell.row];
            window.parent.postMessage({
                type: 'OPEN_CRUD_MODAL',
                mode: 'edit',
                row: {
                    transaction_id: row.transaction_id,
                    item_name: row.item_name,
                    amount: row.amount,
                    fmt_date: row.purchase_date.replace(' ', 'T')
                }
            }, '*');
        }
        
        return null;
    }
    """,
    Output('parent-modal-trigger', 'data'),
    [Input('dash-open-crud-btn', 'n_clicks'),
     Input('transactions-datatable', 'active_cell')],
    [State('transactions-datatable', 'data')],
    prevent_initial_call=True
)

# ----------------------------------------------------------------------------------
# PIPELINE STEP D: Auto-Resize Parent Iframe Height via ResizeObserver
# ----------------------------------------------------------------------------------
app.clientside_callback(
    """
    function(children) {
        const container = document.getElementById('dash-root-container');
        if (!container) return window.dash_clientside.no_update;

        // Attach ResizeObserver once to track any chart, table, or pagination height changes
        if (!window._dashResizeObserverAttached) {
            window._dashResizeObserverAttached = true;
            const sendHeight = () => {
                const height = Math.max(
                    container.scrollHeight,
                    document.body.scrollHeight
                );
                window.parent.postMessage({ type: 'DASH_RESIZE', height: height }, '*');
            };

            const observer = new ResizeObserver(sendHeight);
            observer.observe(container);
            sendHeight();
        }
        return window.dash_clientside.no_update;
    }
    """,
    Output('dash-root-container', 'data-resize-ready'),
    Input('table-output-mount', 'children')
)

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8050, debug=True)
