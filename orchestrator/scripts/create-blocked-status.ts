import "dotenv/config";

const apiKey = process.env.LINEAR_API_KEY;
if (!apiKey) {
  console.error("LINEAR_API_KEY is required");
  process.exit(1);
}

async function gql(
  query: string,
  variables?: Record<string, unknown>
): Promise<Record<string, unknown>> {
  const res = await fetch("https://api.linear.app/graphql", {
    method: "POST",
    headers: { "Content-Type": "application/json", Authorization: apiKey },
    body: JSON.stringify({ query, variables }),
  });
  const json = (await res.json()) as {
    data?: Record<string, unknown>;
    errors?: Array<{ message: string }>;
  };
  if (json.errors?.length) {
    throw new Error(json.errors.map((e) => e.message).join("; "));
  }
  if (!json.data) {
    throw new Error("Linear API returned no data");
  }
  return json.data;
}

async function main(): Promise<void> {
  const teams = (await gql(`query { teams { nodes { id name key } } }`)) as {
    teams: { nodes: Array<{ id: string; name: string; key: string }> };
  };
  const team = teams.teams.nodes.find((t) => t.name === "Modernize");
  if (!team) {
    console.error("Modernize team not found");
    process.exit(1);
  }
  console.log(`Team: ${team.name} (${team.id})`);

  const states = (await gql(
    `query($id: String!) { team(id: $id) { states { nodes { id name type position } } } }`,
    { id: team.id }
  )) as {
    team: { states: { nodes: Array<{ id: string; name: string; type: string; position: number }> } };
  };

  const existing = states.team.states.nodes.find((s) => s.name === "Blocked");
  if (existing) {
    console.log(`Blocked already exists: ${existing.id}`);
    return;
  }

  const inProgress = states.team.states.nodes.find((s) => s.name === "In Progress");
  const position = inProgress ? inProgress.position + 0.5 : 3;

  const created = (await gql(
    `mutation($input: WorkflowStateCreateInput!) {
      workflowStateCreate(input: $input) {
        success
        workflowState { id name type }
      }
    }`,
    {
      input: {
        teamId: team.id,
        name: "Blocked",
        type: "started",
        color: "#eb5757",
        position,
      },
    }
  )) as {
    workflowStateCreate: {
      success: boolean;
      workflowState: { id: string; name: string; type: string };
    };
  };

  console.log("Created:", JSON.stringify(created.workflowStateCreate, null, 2));
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
